<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductInternalCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_product_code_requires_authentication_and_resolves_to_stable_product_id(): void
    {
        $this->getJson('/api/products/resolve?code=PS-00000001')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $productId = DB::table('products')->insertGetId([
            'sku' => 'STABLE-OLD', 'name' => 'แหวนรอง', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $code = 'PS-'.str_pad((string) $productId, 8, '0', STR_PAD_LEFT);

        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.internal_code', $code);
        $this->getJson('/api/products/resolve?code='.strtolower($code))
            ->assertOk()->assertJsonPath('product.id', $productId)->assertJsonPath('product.sku', 'STABLE-OLD');
        $this->putJson("/api/products/{$productId}", [
            'sku' => 'STABLE-NEW', 'name' => 'แหวนรอง', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
        ])->assertOk();
        $this->getJson('/api/products/resolve?code='.$code)
            ->assertOk()->assertJsonPath('product.id', $productId)->assertJsonPath('product.sku', 'STABLE-NEW');
        $this->getJson('/api/products/resolve?code=PS-99999999')->assertNotFound();
    }

    public function test_internal_product_code_can_find_count_item_after_sku_changes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $productId = DB::table('products')->insertGetId([
            'sku' => 'COUNT-OLD', 'name' => 'ฝาครอบ', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $code = 'PS-'.str_pad((string) $productId, 8, '0', STR_PAD_LEFT);
        DB::table('inventory_balances')->insert(['product_id' => $productId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        DB::table('products')->where('id', $productId)->update(['sku' => 'COUNT-NEW']);

        $this->postJson("/api/stock-counts/{$countId}/lookup", ['sku' => $code])
            ->assertOk()->assertJsonPath('status', 'not_counted')->assertJsonPath('item.product.sku', 'COUNT-NEW');
    }
}
