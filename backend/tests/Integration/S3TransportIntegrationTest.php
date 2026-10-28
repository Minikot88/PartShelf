<?php

namespace Tests\Integration;

use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class S3TransportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_direct_upload_immutable_image_workbook_export_and_cleanup_on_local_s3(): void
    {
        $this->assertSame('http://s3:9000', getenv('S3_TEST_ENDPOINT'), 'This integration test may only contact the DP01 local emulator.');
        $config = ['driver' => 's3', 'key' => 'partshelf-local-test', 'secret' => 'partshelf-local-test-not-for-cloud', 'region' => 'us-east-1', 'endpoint' => 'http://s3:9000', 'bucket' => 'partshelf-dp01-tests', 'use_path_style_endpoint' => true, 'throw' => true];
        config(['filesystems.disks.r2' => $config, 'partshelf.file_transport' => 'object', 'partshelf.image_disk' => 'r2']);
        Storage::forgetDisk('r2');
        $client = new S3Client(['version' => 'latest', 'region' => $config['region'], 'endpoint' => $config['endpoint'], 'use_path_style_endpoint' => true, 'credentials' => ['key' => $config['key'], 'secret' => $config['secret']]]);
        if (! $client->doesBucketExistV2($config['bucket'])) {
            $client->createBucket(['Bucket' => $config['bucket']]);
        }
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7qkAAAAASUVORK5CYII=');
        $png = str_pad($png, 5242880, "\0");
        $intent = $this->upload('image', $png, 'image/png');
        $product = $this->postJson('/api/products', ['sku' => 'S3-IMAGE', 'name' => 'Private', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0, 'image_token' => $intent['token']])->assertCreated()->json('product');
        $signed = $this->get($product['image_url'])->assertRedirect()->headers->get('location');
        $this->assertSame($png, Http::get($signed)->throw()->body());
        $unsigned = strtok($signed, '?');
        $this->assertSame(403, Http::get($unsigned)->status());
        // A still-valid upload URL can only mutate staging, never the already published image.
        Http::withBody(str_repeat('X', strlen($png)), 'image/png')->put($intent['upload_url'])->throw();
        $this->assertSame($png, Http::get($signed)->throw()->body());

        $path = tempnam(sys_get_temp_dir(), 'dp01-workbook-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name']));
        $writer->addRow(Row::fromValues(['S3-IMPORT', 'Imported', '', 'ชิ้น', '0', '']));
        $writer->close();
        try {
            $workbook = $this->upload('workbook', file_get_contents($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } finally {
            unlink($path);
        }
        $preview = $this->postJson('/api/products/import/preview', ['upload_token' => $workbook['token']])->assertOk()->assertJsonPath('valid_count', 1)->json();
        $this->postJson('/api/products/import/confirm', ['token' => $preview['token']])->assertCreated();
        $this->postJson('/api/products/import/preview', ['upload_token' => $workbook['token']])->assertUnprocessable();
        $export = $this->getJson('/api/products/export')->assertOk()->json();
        $this->assertStringStartsWith('PK', Http::get($export['download_url'])->throw()->body());
        $exportKey = DB::table('file_transfers')->where('kind', 'export')->value('object_key');
        $stagingKey = DB::table('file_transfers')->where('id', $intent['token'])->value('object_key');
        $this->travel(26)->hours();
        $this->artisan('partshelf:files:cleanup')->assertSuccessful();
        $this->assertFalse(Storage::disk('r2')->exists($exportKey));
        $this->assertFalse(Storage::disk('r2')->exists($stagingKey));
        $this->assertTrue(Storage::disk('r2')->exists($product['image_path']));
    }

    private function upload(string $kind, string $bytes, string $mime): array
    {
        $intent = $this->postJson('/api/file-transfers', ['kind' => $kind, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)])->assertCreated()->json();
        Http::withBody($bytes, $mime)->put($intent['upload_url'])->throw();

        return $intent;
    }
}
