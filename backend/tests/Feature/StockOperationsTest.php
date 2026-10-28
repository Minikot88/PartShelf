<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_endpoints_require_authentication(): void
    {
        $this->getJson('/api/stock/balances')->assertUnauthorized();
        $this->postJson('/api/stock/opening-balances', [])->assertUnauthorized();
        $this->postJson('/api/stock/receipts', [])->assertUnauthorized();
    }

    public function test_opening_balance_and_receipt_update_ledger_and_current_balance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $product = $this->createProduct('STOCK-001', 3);

        $this->withHeader('Idempotency-Key', 'f9dc7029-b769-4d79-9a44-c65f35de6b3f')
            ->postJson('/api/stock/opening-balances', [
                'product_id' => $product,
                'quantity' => 20,
                'reason' => 'ยอดยกมาต้นงวด',
            ])->assertCreated()
            ->assertJsonPath('movement.movement_type', 'opening')
            ->assertJsonPath('balance.quantity', '20.000');

        $this->withHeader('Idempotency-Key', '88d083ca-d1f0-46cf-a50c-5006da1e7a37')
            ->postJson('/api/stock/receipts', [
                'product_id' => $product,
                'quantity' => 5,
                'reason' => 'รับสินค้าเข้าร้าน',
            ])->assertCreated()
            ->assertJsonPath('movement.movement_type', 'receipt')
            ->assertJsonPath('balance.quantity', '25.000');

        $this->assertDatabaseCount('stock_movements', 2);
        $this->getJson('/api/stock/balances?search=STOCK-001')
            ->assertOk()
            ->assertJsonPath('data.0.quantity', '25.000')
            ->assertJsonPath('data.0.stock_status', 'ok');
        $this->getJson("/api/products/{$product}/movements")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_zero_opening_balance_is_recorded_and_opening_can_only_be_set_once(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $product = $this->createProduct('STOCK-002', 0);

        $this->withHeader('Idempotency-Key', '0f5a111c-e3bf-43a1-8448-e34c27751a61')
            ->postJson('/api/stock/opening-balances', ['product_id' => $product, 'quantity' => 0])
            ->assertCreated()
            ->assertJsonPath('balance.quantity', '0.000');

        $this->withHeader('Idempotency-Key', 'f5fdf009-88b0-40a9-b4c0-7100af792d38')
            ->postJson('/api/stock/opening-balances', ['product_id' => $product, 'quantity' => 4])
            ->assertStatus(409);

        $this->getJson('/api/stock/balances?search=STOCK-002')
            ->assertOk()
            ->assertJsonPath('data.0.stock_status', 'out');
    }

    public function test_receipt_idempotency_replays_same_operation_and_rejects_reused_key_with_new_payload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $product = $this->createProduct('STOCK-003', 1);
        $key = '28bd86e8-621e-44a9-a46f-8786e544b848';
        $payload = ['product_id' => $product, 'quantity' => '1.250'];

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock/receipts', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock/receipts', $payload)
            ->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('balance.quantity', '1.250');
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/stock/receipts', ['product_id' => $product, 'quantity' => 2])
            ->assertStatus(409);

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product, 'quantity' => 1.25]);
    }

    public function test_receipt_requires_positive_quantity_and_staff_cannot_write_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner);
        $product = $this->createProduct('STOCK-004', 2);
        foreach ([0, -1] as $quantity) {
            $this->withHeader('Idempotency-Key', fake()->uuid())
                ->postJson('/api/stock/receipts', ['product_id' => $product, 'quantity' => $quantity])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('quantity');
        }

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->postJson('/api/stock/receipts', ['product_id' => $product, 'quantity' => 1])
            ->assertForbidden();
    }

    private function createProduct(string $sku, float $minimum): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => 'อะไหล่สต๊อกทดสอบ',
            'base_unit' => 'ชิ้น',
            'min_stock_quantity' => $minimum,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
