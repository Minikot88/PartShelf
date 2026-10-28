<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorageLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_catalog_requires_authentication(): void
    {
        $this->getJson('/api/locations')->assertUnauthorized();
        $this->postJson('/api/locations', $this->locationPayload())->assertUnauthorized();
    }

    public function test_owner_can_create_update_and_search_locations_by_path(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $id = $this->postJson('/api/locations', $this->locationPayload())
            ->assertCreated()
            ->assertJsonPath('location.zone', 'A')
            ->json('location.id');

        $this->putJson("/api/locations/{$id}", [
            'zone' => 'B',
            'shelf' => '02',
            'level' => '01',
            'bin' => '03',
        ])->assertOk()->assertJsonPath('location.zone', 'B');

        $this->getJson('/api/locations?search=B-02-01-03')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.path', 'B-02-01-03');
    }

    public function test_location_path_is_unique_case_insensitively(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $this->postJson('/api/locations', $this->locationPayload())->assertCreated();

        $this->postJson('/api/locations', [
            'zone' => 'a',
            'shelf' => '01',
            'level' => '01',
            'bin' => '01',
        ])->assertUnprocessable()->assertJsonValidationErrors('bin');
    }

    public function test_staff_can_view_locations_but_cannot_edit_them(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $location = $this->postJson('/api/locations', $this->locationPayload())->assertCreated()->json('location.id');

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson('/api/locations')
            ->assertOk();

        $this->postJson('/api/locations', $this->locationPayload('C'))->assertForbidden();
        $this->putJson("/api/locations/{$location}", $this->locationPayload('D'))->assertForbidden();
    }

    public function test_assigning_product_to_a_location_does_not_change_its_balance_and_is_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $first = $this->postJson('/api/locations', $this->locationPayload('A'))->assertCreated()->json('location.id');
        $second = $this->postJson('/api/locations', $this->locationPayload('B'))->assertCreated()->json('location.id');
        $product = DB::table('products')->insertGetId([
            'sku' => 'LOC-001',
            'name' => 'อะไหล่ย้ายตำแหน่ง',
            'base_unit' => 'ชิ้น',
            'storage_location_id' => $first,
            'min_stock_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'product_id' => $product,
            'quantity' => 12.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/products/{$product}/location", ['storage_location_id' => $second])
            ->assertOk()
            ->assertJsonPath('product.storage_location_id', $second);

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 12.5]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'product',
            'entity_id' => $product,
            'action' => 'product.location_changed',
        ]);

        $this->getJson("/api/locations/{$second}/products")
            ->assertOk()
            ->assertJsonPath('data.0.id', $product);
    }

    private function locationPayload(string $zone = 'A'): array
    {
        return ['zone' => $zone, 'shelf' => '01', 'level' => '01', 'bin' => '01'];
    }
}
