<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObjectFileTransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['partshelf.file_transport' => 'object', 'partshelf.image_disk' => 'r2']);
        config(['filesystems.disks.r2' => ['driver' => 's3', 'key' => 'local-test-only', 'secret' => 'local-test-only', 'region' => 'auto', 'bucket' => 'partshelf-test', 'endpoint' => 'http://127.0.0.1:19000', 'use_path_style_endpoint' => true]]);
        Storage::fake('r2');
    }

    public function test_private_image_intent_is_bound_validated_and_consumed_once(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->getJson('/api/file-transport')->assertUnauthorized();
        $this->actingAs($owner);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7qkAAAAASUVORK5CYII=');
        $intent = $this->intent('image', $png, 'image/png');
        Storage::disk('r2')->put($intent['object_key'], $png);
        $fields = ['sku' => 'PRIVATE-1', 'name' => 'Private image', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_token' => $intent['token']];

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->postJson('/api/products', $fields)->assertUnprocessable();
        $response = $this->actingAs($owner)->postJson('/api/products', $fields)->assertCreated();
        $id = $response->json('product.id');
        $this->assertSame("/api/products/{$id}/image", $response->json('product.image_url'));
        $this->assertNotSame($intent['object_key'], $response->json('product.image_path'));
        Storage::disk('r2')->assertExists($response->json('product.image_path'));
        $this->get("/api/products/{$id}/image")->assertRedirect()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/products', [...$fields, 'sku' => 'PRIVATE-2'])->assertUnprocessable();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_uploaded_bytes_and_real_file_type_must_match_before_product_is_written(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $intent = $this->intent('image', '<svg>bad</svg>', 'image/png');
        Storage::disk('r2')->put($intent['object_key'], '<svg>bad</svg>');
        $fields = ['sku' => 'BAD-1', 'name' => 'Bad', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_token' => $intent['token']];
        $this->postJson('/api/products', $fields)->assertUnprocessable();
        Storage::disk('r2')->put($intent['object_key'], 'other bytes');
        $this->postJson('/api/products', $fields)->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_expiry_permissions_and_payload_limits_are_enforced(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->postJson('/api/file-transfers', [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $this->postJson('/api/file-transfers', ['kind' => 'image', 'size' => 5242881, 'mime' => 'image/png', 'sha256' => str_repeat('a', 64)])->assertUnprocessable();
        $intent = $this->intent('image', 'bytes', 'image/png');
        $this->travel(16)->minutes();
        $this->postJson('/api/products', ['sku' => 'EXPIRED', 'name' => 'Expired', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_token' => $intent['token']])->assertUnprocessable();
    }

    public function test_object_exports_return_small_private_download_descriptors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $response = $this->getJson('/api/products/export')->assertOk();
        $this->assertStringContainsString('X-Amz-Signature', $response->json('download_url'));
        $this->assertDatabaseHas('file_transfers', ['kind' => 'export']);
        $this->assertLessThan(4096, strlen($response->getContent()));
    }

    public function test_failed_outer_commit_keeps_the_previous_product_image(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7qkAAAAASUVORK5CYII=');
        Storage::disk('r2')->put('products/old.png', $png);
        $id = DB::table('products')->insertGetId(['sku' => 'ROLLBACK-IMAGE', 'name' => 'Original', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_path' => 'products/old.png', 'created_at' => now(), 'updated_at' => now()]);
        $intent = $this->intent('image', $png, 'image/png');
        Storage::disk('r2')->put($intent['object_key'], $png);
        $injected = false;
        DB::listen(function ($query) use (&$injected) {
            if (! $injected && str_starts_with($query->sql, 'update "file_transfers"')) {
                $injected = true;
                throw new \RuntimeException('Injected outer transaction failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->putJson('/api/products/'.$id, ['sku' => 'ROLLBACK-IMAGE', 'name' => 'Replacement', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_token' => $intent['token']]);
            $this->fail('Expected the injected transaction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected outer transaction failure', $exception->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Original', 'image_path' => 'products/old.png']);
        Storage::disk('r2')->assertExists('products/old.png');
    }

    private function intent(string $kind, string $bytes, string $mime): array
    {
        $response = $this->postJson('/api/file-transfers', ['kind' => $kind, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)])->assertCreated();
        $this->assertStringContainsString('X-Amz-Signature', $response->json('upload_url'));
        $this->assertStringNotContainsString('secret', $response->getContent());

        return [...$response->json(), 'object_key' => DB::table('file_transfers')->where('id', $response->json('token'))->value('object_key')];
    }
}
