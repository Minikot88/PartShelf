<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockCountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_endpoints_require_authentication(): void
    {
        $this->getJson('/api/stock-counts')->assertUnauthorized();
        $this->postJson('/api/stock-counts', [])->assertUnauthorized();
    }

    public function test_immediate_count_snapshots_all_balances_and_location_count_scopes_products(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $firstLocation = $this->createLocation('A');
        $secondLocation = $this->createLocation('B');
        $firstProduct = $this->createProduct('COUNT-1', $firstLocation, 7.5);
        $secondProduct = $this->createProduct('COUNT-2', $secondLocation, 0);
        $this->createProduct('COUNT-3', null, 99);

        $immediate = $this->postJson('/api/stock-counts', [])->assertCreated()
            ->assertJsonPath('count.status', 'open')
            ->assertJsonPath('count.item_count', 3);
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $immediate->json('count.id'),
            'product_id' => $firstProduct,
            'reference_quantity' => 7.5,
        ]);

        $locationCount = $this->postJson('/api/stock-counts', ['storage_location_id' => $firstLocation])->assertCreated()
            ->assertJsonPath('count.item_count', 1);
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $locationCount->json('count.id'),
            'product_id' => $firstProduct,
            'storage_location_id' => $firstLocation,
        ]);
        $this->assertDatabaseMissing('stock_count_items', [
            'stock_count_id' => $locationCount->json('count.id'),
            'product_id' => $secondProduct,
        ]);
    }

    public function test_count_can_record_zero_without_changing_inventory_and_status_distinguishes_not_counted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $product = $this->createProduct('COUNT-ZERO', null, 4);
        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $item = DB::table('stock_count_items')->where('stock_count_id', $countId)->first();

        $this->getJson("/api/stock-counts/{$countId}")
            ->assertOk()->assertJsonPath('items.0.count_status', 'not_counted');
        $this->postJson("/api/stock-counts/{$countId}/items/{$item->id}/counts", ['counted_quantity' => 0])
            ->assertCreated()->assertJsonPath('item.count_status', 'counted_zero')
            ->assertJsonPath('item.counted_quantity', '0.000');

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 4]);
        $this->assertDatabaseHas('stock_count_attempts', ['stock_count_item_id' => $item->id, 'counted_quantity' => 0]);
    }

    public function test_counted_status_and_lookup_report_unknown_sku_as_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $product = $this->createProduct('COUNT-FOUND', null, 2);
        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $item = DB::table('stock_count_items')->where('stock_count_id', $countId)->where('product_id', $product)->first();

        $this->postJson("/api/stock-counts/{$countId}/lookup", ['sku' => 'missing-1'])
            ->assertNotFound()->assertJsonPath('status', 'not_found');
        $this->postJson("/api/stock-counts/{$countId}/lookup", ['sku' => 'count-found'])
            ->assertOk()->assertJsonPath('status', 'not_counted')->assertJsonPath('item.id', $item->id);
        $this->postJson("/api/stock-counts/{$countId}/items/{$item->id}/counts", ['counted_quantity' => '3.250'])
            ->assertCreated()->assertJsonPath('item.count_status', 'counted');
        $this->getJson("/api/stock-counts/{$countId}")
            ->assertOk()->assertJsonPath('items.0.counted_quantity', '3.250');
    }

    public function test_count_rejects_negative_quantity_duplicate_entry_and_foreign_item(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $product = $this->createProduct('COUNT-VALIDATION', null, 8);
        $otherProduct = $this->createProduct('COUNT-OTHER', null, 1);
        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $item = DB::table('stock_count_items')->where('stock_count_id', $countId)->where('product_id', $product)->first();
        $otherItem = DB::table('stock_count_items')->where('stock_count_id', $countId)->where('product_id', $otherProduct)->first();

        $this->postJson("/api/stock-counts/{$countId}/items/{$item->id}/counts", ['counted_quantity' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('counted_quantity');
        $this->postJson("/api/stock-counts/{$countId}/items/{$item->id}/counts", ['counted_quantity' => 7])->assertCreated();
        $this->postJson("/api/stock-counts/{$countId}/items/{$item->id}/counts", ['counted_quantity' => 6])->assertStatus(409);
        $otherCount = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $this->postJson("/api/stock-counts/{$otherCount}/items/{$otherItem->id}/counts", ['counted_quantity' => 1])->assertNotFound();
    }

    private function createLocation(string $zone): int
    {
        return DB::table('storage_locations')->insertGetId([
            'zone' => $zone, 'shelf' => '1', 'level' => '1', 'bin' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createProduct(string $sku, ?int $location, float $balance): int
    {
        $product = DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => 'อะไหล่ตรวจนับ', 'base_unit' => 'ชิ้น', 'storage_location_id' => $location,
            'min_stock_quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'product_id' => $product, 'quantity' => $balance, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $product;
    }
}
