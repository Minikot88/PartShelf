<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockCountCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_attempt_keeps_its_movement_aware_variance_and_does_not_change_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $product = $this->createProduct('VARIANCE-1', 10);
        [$countId, $itemId] = $this->startCount($product);

        $this->receive($product, '5.000', 'd5968370-caf0-4a58-9846-6236b8e5fb22');
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/counts", ['counted_quantity' => 13])->assertCreated();
        $this->receive($product, '2.000', '185ded63-c3ed-4e27-8bd4-f46955e50460');
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/recounts", ['counted_quantity' => 16])->assertCreated();

        $response = $this->getJson("/api/stock-counts/{$countId}")->assertOk();
        $response->assertJsonPath('items.0.variance', '-1.000')
            ->assertJsonPath('items.0.attempts.0.variance', '-2.000')
            ->assertJsonPath('items.0.attempts.1.variance', '-1.000')
            ->assertJsonPath('items.0.attempts.1.attempt_number', 2);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 17]);
        $this->assertDatabaseCount('stock_count_attempts', 2);
    }

    public function test_owner_manager_correction_applies_latest_variance_to_current_balance_and_replays_safely(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $product = $this->createProduct('CORRECT-1', 10);
        [$countId, $itemId] = $this->startCount($product);
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/counts", ['counted_quantity' => 8])->assertCreated();
        $this->receive($product, '5.000', 'd71a7688-861b-42bb-8f89-ad4d1ab1c02f');
        $key = 'e01d6e0b-83e0-46ac-93d8-09cf53f64c14';

        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'ตรวจสอบชั้นวางแล้ว'])
            ->assertCreated()->assertJsonPath('correction.variance', '-2.000')
            ->assertJsonPath('correction.balance_after', '13.000');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'ตรวจสอบชั้นวางแล้ว'])
            ->assertOk()->assertJsonPath('replayed', true);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'เปลี่ยนเหตุผล'])
            ->assertStatus(409);

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 13]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product, 'movement_type' => 'correction', 'quantity_delta' => -2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_count.corrected', 'entity_type' => 'stock_count_attempt']);
    }

    public function test_staff_cannot_correct_and_a_correction_cannot_make_balance_negative(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $product = $this->createProduct('CORRECT-2', 10);
        [$countId, $itemId] = $this->startCount($product);
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/counts", ['counted_quantity' => 0])->assertCreated();

        DB::table('inventory_balances')->where('product_id', $product)->update(['quantity' => 5]);
        DB::table('stock_movements')->insert([
            'product_id' => $product, 'movement_type' => 'reversal', 'quantity_delta' => -5, 'reason' => 'concurrent stock event',
            'occurred_at' => now()->addSecond(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withHeader('Idempotency-Key', '8eab9346-5132-43c4-940c-30bdcc66c655')
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'นับของหาย'])
            ->assertStatus(409);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 5]);
        $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'correction']);

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->withHeader('Idempotency-Key', 'e61a27b7-9491-4f95-8dd1-177577c78808')
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'พยายามแก้'])
            ->assertForbidden();
    }

    public function test_correction_creates_missing_zero_balance_for_counted_product(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $product = DB::table('products')->insertGetId([
            'sku' => 'CORRECT-EMPTY', 'name' => 'สินค้าไม่เคยรับเข้า', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        [$countId, $itemId] = $this->startCount($product);
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/counts", ['counted_quantity' => 5])->assertCreated();
        $this->withHeader('Idempotency-Key', 'c6c53da3-b02a-4650-906c-4f5575119630')
            ->postJson("/api/stock-counts/{$countId}/items/{$itemId}/correction", ['reason' => 'ตรวจพบสินค้าในชั้น'])
            ->assertCreated()->assertJsonPath('correction.balance_after', '5.000');
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 5]);
    }

    private function createProduct(string $sku, float $quantity): int
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => 'อะไหล่ตรวจผลต่าง', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_balances')->insert([
            'product_id' => $productId, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $productId;
    }

    private function startCount(int $productId): array
    {
        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $itemId = DB::table('stock_count_items')->where('stock_count_id', $countId)->where('product_id', $productId)->value('id');

        return [$countId, $itemId];
    }

    private function receive(int $productId, string $quantity, string $key): void
    {
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/stock/receipts', ['product_id' => $productId, 'quantity' => $quantity])->assertCreated();
    }
}
