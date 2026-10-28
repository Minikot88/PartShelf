<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_owner_can_create_and_update_a_product_with_multiple_vehicle_fitments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $category = $this->postJson('/api/categories', ['name' => 'เครื่องยนต์'])->assertCreated()
            ->assertJsonPath('category.name', 'เครื่องยนต์')
            ->json('category.id');

        $response = $this->postJson('/api/products', [
            'sku' => 'ENG-001',
            'name' => 'กรองน้ำมันเครื่อง',
            'part_number' => 'OF-123',
            'base_unit' => 'ชิ้น',
            'min_stock_quantity' => 2,
            'category_id' => $category,
            'fitments' => [
                ['make' => 'Toyota', 'model' => 'Hilux', 'year' => 2020, 'engine' => '2GD'],
                ['make' => 'Toyota', 'model' => 'Fortuner', 'year' => 2021, 'engine' => '2GD'],
            ],
        ])->assertCreated()
            ->assertJsonPath('product.sku', 'ENG-001')
            ->assertJsonPath('product.fitments.0.model', 'Fortuner');

        $id = $response->json('product.id');

        $this->putJson("/api/products/{$id}", [
            'sku' => 'ENG-001-R2',
            'name' => 'กรองน้ำมันเครื่อง รุ่นปรับปรุง',
            'part_number' => 'OF-123-R2',
            'base_unit' => 'ชิ้น',
            'min_stock_quantity' => 3,
            'category_id' => $category,
            'fitments' => [
                ['make' => 'Toyota', 'model' => 'Hilux', 'year' => 2022, 'engine' => '2GD'],
            ],
        ])->assertOk()
            ->assertJsonPath('product.id', $id)
            ->assertJsonPath('product.sku', 'ENG-001-R2')
            ->assertJsonCount(1, 'product.fitments');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'product',
            'entity_id' => $id,
            'action' => 'product.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'product',
            'entity_id' => $id,
            'action' => 'product.updated',
        ]);
    }

    public function test_duplicate_sku_is_rejected_without_case_sensitive_bypass(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $this->createProduct('BRAKE-01');

        $this->postJson('/api/products', $this->productPayload('brake-01'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_categories_are_limited_to_two_levels(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $root = $this->postJson('/api/categories', ['name' => 'เครื่องยนต์'])->assertCreated()->json('category.id');
        $child = $this->postJson('/api/categories', ['name' => 'กรอง', 'parent_id' => $root])
            ->assertCreated()->json('category.id');

        $this->postJson('/api/categories', ['name' => 'กรองน้ำมัน', 'parent_id' => $child])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_staff_can_search_products_but_cannot_change_catalog(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner);
        $this->createProduct('FILTER-77', 'กรองอากาศ');

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson('/api/products?search=FILTER-77')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'FILTER-77');

        $this->postJson('/api/products', $this->productPayload('FILTER-78'))
            ->assertForbidden();
    }

    public function test_product_search_matches_part_number_and_vehicle_fitment(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $payload = $this->productPayload('PLUG-22', 'หัวเทียน');
        $payload['part_number'] = 'NGK-PLZKAR7A';
        $payload['fitments'] = [['make' => 'Honda', 'model' => 'Civic', 'year' => 2022, 'engine' => '1.5T']];
        $this->postJson('/api/products', $payload)->assertCreated();

        foreach (['NGK-PLZKAR7A', 'Civic', 'Honda'] as $term) {
            $this->getJson('/api/products?search='.urlencode($term))
                ->assertOk()
                ->assertJsonPath('data.0.sku', 'PLUG-22');
        }
    }

    public function test_product_image_is_stored_on_the_local_public_disk(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $response = $this->post('/api/products', $this->productPayload('PHOTO-01') + [
            'image' => UploadedFile::fake()->createWithContent(
                'part.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/s0cAAAAASUVORK5CYII='),
            ),
        ])->assertCreated();

        $path = $response->json('product.image_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_invalid_fitment_year_and_negative_minimum_stock_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->postJson('/api/products', array_merge($this->productPayload('INVALID-01'), [
            'min_stock_quantity' => -1,
            'fitments' => [['make' => 'Toyota', 'model' => 'Hilux', 'year' => 1800]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['min_stock_quantity', 'fitments.0.year']);
    }

    public function test_category_names_are_unique_without_case_sensitive_bypass(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $this->postJson('/api/categories', ['name' => 'เครื่องยนต์'])->assertCreated();

        $this->postJson('/api/categories', ['name' => 'เครื่องยนต์'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, DB::table('categories')->whereNull('parent_id')->count());
    }

    public function test_category_can_be_renamed_and_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $category = $this->postJson('/api/categories', ['name' => 'ระบบเบรก'])->assertCreated()->json('category.id');

        $this->putJson("/api/categories/{$category}", ['name' => 'ระบบเบรกและคลัตช์'])
            ->assertOk()
            ->assertJsonPath('category.name', 'ระบบเบรกและคลัตช์');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'category',
            'entity_id' => $category,
            'action' => 'category.updated',
        ]);
    }

    public function test_unsupported_product_image_format_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $this->post('/api/products', $this->productPayload('VECTOR-01') + [
            'image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml'),
        ])->assertUnprocessable();
    }

    private function createProduct(string $sku, string $name = 'อะไหล่ทดสอบ'): void
    {
        $this->postJson('/api/products', $this->productPayload($sku, $name))->assertCreated();
    }

    private function productPayload(string $sku, string $name = 'อะไหล่ทดสอบ'): array
    {
        return [
            'sku' => $sku,
            'name' => $name,
            'part_number' => null,
            'base_unit' => 'ชิ้น',
            'min_stock_quantity' => 0,
            'category_id' => null,
            'fitments' => [],
        ];
    }
}
