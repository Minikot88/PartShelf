<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmartStockNotesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        return $user;
    }

    private function createNote(array $data = []): array
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/stock-notes/products', array_merge(['name' => 'อะไหล่ทดสอบ', 'tracking_mode' => 'QUANTITY'], $data))
            ->assertCreated()->json('product');
    }

    private function action(array $product, string $action, array $data = [], ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
            ->postJson('/api/stock-notes/products/'.$product['id'].'/actions', array_merge(['action' => $action], $data));
    }

    public function test_quantity_sales_receipts_and_retry_are_real_atomic_movements(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $this->assertSame('10.000', $product['quantity']);
        $key = (string) Str::uuid();
        $product = $this->action($product, 'sale', ['quantity' => '1'], $key)->assertCreated()->assertJsonPath('product.quantity', '9.000')->json('product');
        $this->action($product, 'sale', ['quantity' => '1'], $key)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('product.quantity', '9.000');
        $this->action($product, 'sale', ['quantity' => '2'], $key)->assertStatus(409);
        $product = $this->action($product, 'sale', ['quantity' => '9'])->assertCreated()->assertJsonPath('product.quantity', '0.000')->assertJsonPath('product.stock_status', 'OUT_OF_STOCK')->json('product');
        $this->action($product, 'sale', ['quantity' => '1'])->assertStatus(409);
        $this->action($product, 'receipt', ['quantity' => '5'])->assertCreated()->assertJsonPath('product.quantity', '5.000')->assertJsonPath('product.stock_status', 'IN_STOCK');
        $this->assertDatabaseCount('stock_movements', 4);
        $this->assertSame('5.000', DB::table('inventory_balances')->where('product_id', $product['id'])->value('quantity'));
    }

    public function test_quick_add_without_sku_does_not_turn_unset_quantity_into_zero(): void
    {
        $this->owner();
        $product = $this->createNote();
        $this->assertNull($product['quantity']);
        $this->assertSame('UNSET', $product['stock_status']);
        $this->assertTrue($product['sku_is_generated']);
        $this->assertStringStartsWith('PS-', $product['internal_code']);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->action($product, 'sale', ['quantity' => '1'])->assertStatus(409);
        $this->action($product, 'receipt', ['quantity' => '1'])->assertStatus(409);
        $this->action($product, 'set_quantity', ['quantity' => '0', 'expected_version' => $product['stock_version'], 'reason' => 'ตรวจแล้วไม่มีสินค้า'])
            ->assertCreated()->assertJsonPath('product.quantity', '0.000')->assertJsonPath('product.stock_status', 'OUT_OF_STOCK');
    }

    public function test_availability_unknown_in_out_and_history_are_distinct_without_balance(): void
    {
        $this->owner();
        $product = $this->createNote(['tracking_mode' => 'AVAILABILITY_ONLY']);
        $this->assertSame('UNKNOWN', $product['stock_status']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        foreach (['IN_STOCK', 'OUT_OF_STOCK', 'IN_STOCK', 'UNKNOWN'] as $status) {
            $product = $this->action($product, 'availability', ['availability_status' => $status, 'expected_version' => $product['stock_version']])
                ->assertCreated()->assertJsonPath('product.quantity', null)->assertJsonPath('product.stock_status', $status)
                ->assertJsonPath('product.status_updated_by', $staff->id)->json('product');
        }
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->getJson('/api/stock-notes/products/'.$product['id'].'/history')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/stock-notes/products?stock_status=UNKNOWN')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/stock-notes/products?stock_status=OUT_OF_STOCK')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_absolute_set_rejects_a_stale_snapshot_after_another_users_sale(): void
    {
        $owner = $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $snapshot = $product['stock_version'];
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->action($product, 'sale', ['quantity' => '1'])->assertCreated()->assertJsonPath('product.quantity', '9.000');
        $this->actingAs($owner);
        $this->action($product, 'set_quantity', ['quantity' => '7', 'expected_version' => $snapshot, 'reason' => 'ยอดจากหน้าจอเก่า'])->assertStatus(409);
        $this->assertSame('9.000', DB::table('inventory_balances')->where('product_id', $product['id'])->value('quantity'));
    }

    public function test_metadata_archive_restore_and_history_preserve_the_product_and_ledger(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $location = DB::table('storage_locations')->insertGetId(['zone' => 'M', 'shelf' => '1', 'level' => '1', 'bin' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $product = $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson('/api/stock-notes/products/'.$product['id'], [
            'name' => 'ชื่อแก้ไขแล้ว', 'storage_location_id' => $location, 'notes' => 'อยู่หน้าร้าน', 'expected_version' => $product['stock_version'],
        ])->assertOk()->assertJsonPath('product.name', 'ชื่อแก้ไขแล้ว')->assertJsonPath('product.storage_location_id', $location)->json('product');
        $product = $this->action($product, 'archive', ['expected_version' => $product['stock_version']])->assertCreated()->json('product');
        $this->assertNotNull($product['archived_at']);
        $this->getJson('/api/stock-notes/products')->assertJsonCount(0, 'data');
        $this->getJson('/api/stock-notes/products?archived=only')->assertJsonCount(1, 'data');
        $this->action($product, 'sale', ['quantity' => '1'])->assertStatus(409);
        $this->action($product, 'restore', ['expected_version' => $product['stock_version']])->assertCreated()->assertJsonPath('product.archived_at', null);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->getJson('/api/stock-notes/products/'.$product['id'].'/history')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_mode_changes_require_confirmation_and_explicit_new_quantity_without_erasing_history(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $path = '/api/stock-notes/products/'.$product['id'];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($path, ['tracking_mode' => 'AVAILABILITY_ONLY', 'expected_version' => $product['stock_version']])->assertUnprocessable();
        $product = $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($path, ['tracking_mode' => 'AVAILABILITY_ONLY', 'confirm_mode_change' => true, 'reason' => 'ไม่ต้องติดตามจำนวน', 'expected_version' => $product['stock_version']])
            ->assertOk()->assertJsonPath('product.quantity', null)->assertJsonPath('product.stock_status', 'UNKNOWN')->json('product');
        $this->assertSame('10.000', DB::table('inventory_balances')->where('product_id', $product['id'])->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($path, ['tracking_mode' => 'QUANTITY', 'confirm_mode_change' => true, 'reason' => 'กลับมานับจำนวน', 'expected_version' => $product['stock_version']])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($path, ['tracking_mode' => 'QUANTITY', 'initial_quantity' => '7', 'confirm_mode_change' => true, 'reason' => 'นับจริงได้เจ็ด', 'expected_version' => $product['stock_version']])
            ->assertOk()->assertJsonPath('product.quantity', '7.000');
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame('-3.000', DB::table('stock_movements')->orderByDesc('id')->value('quantity_delta'));
    }

    public function test_staff_permissions_and_reorder_notes_are_enforced_by_backend(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock-notes/products', ['name' => 'Denied', 'tracking_mode' => 'QUANTITY'])->assertForbidden();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson('/api/stock-notes/products/'.$product['id'], ['name' => 'Denied', 'role' => 'owner', 'expected_version' => $product['stock_version']])->assertForbidden();
        foreach (['receipt', 'set_quantity', 'archive', 'restore'] as $action) {
            $this->action($product, $action, ['quantity' => '1', 'reason' => 'ห้ามแก้เอง', 'expected_version' => $product['stock_version']])->assertForbidden();
        }
        $this->action($product, 'reorder', ['reorder_needed' => true, 'reorder_note' => 'สั่งเพิ่มสองแพ็ก', 'expected_version' => $product['stock_version']])
            ->assertCreated()->assertJsonPath('product.reorder_needed', true)->assertJsonPath('product.quantity', '10.000');
        $this->getJson('/api/stock-notes/products?reorder=1')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('staff', $staff->fresh()->role->value);
    }

    public function test_quantity_low_out_and_manual_reorder_filters_do_not_include_unknowns(): void
    {
        $this->owner();
        $this->createNote(['name' => 'Low', 'initial_quantity' => '2', 'min_stock_quantity' => '2']);
        $this->createNote(['name' => 'Out', 'initial_quantity' => '0']);
        $this->createNote(['name' => 'Unset']);
        $this->createNote(['name' => 'Unknown', 'tracking_mode' => 'AVAILABILITY_ONLY']);
        $this->getJson('/api/stock-notes/products?reorder=1')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/stock-notes/products?stock_status=LOW_STOCK')->assertJsonCount(1, 'data');
        $this->getJson('/api/stock-notes/products?stock_status=UNSET')->assertJsonCount(1, 'data')->assertJsonPath('data.0.quantity', null);
    }

    public function test_legacy_writes_cannot_bypass_modes_archive_or_snapshot_version(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => '10']);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock/receipts', ['product_id' => $product['id'], 'quantity' => 1])->assertCreated();
        $this->action($product, 'set_quantity', ['quantity' => 7, 'reason' => 'stale', 'expected_version' => $product['stock_version']])->assertStatus(409);
        $availability = $this->createNote(['tracking_mode' => 'AVAILABILITY_ONLY']);
        $unset = $this->createNote();
        foreach ([$availability, $unset] as $p) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock/receipts', ['product_id' => $p['id'], 'quantity' => 1])->assertStatus(409);
        }
        $count = $this->postJson('/api/stock-counts')->assertCreated()->json('count.id');
        $this->assertSame(1, DB::table('stock_count_items')->where('stock_count_id', $count)->count());
        $current = $this->getJson('/api/stock-notes/products?search='.$product['internal_code'])->json('data.0');
        $this->action($current, 'archive', ['expected_version' => $current['stock_version']])->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock/receipts', ['product_id' => $product['id'], 'quantity' => 1])->assertStatus(409);
        $this->assertSame('11.000', DB::table('inventory_balances')->where('product_id', $product['id'])->value('quantity'));
    }

    public function test_auth_validation_and_replayed_create_do_not_duplicate_or_escalate(): void
    {
        $this->getJson('/api/stock-notes/products')->assertUnauthorized();
        $owner = $this->owner();
        $key = (string) Str::uuid();
        $payload = ['sku' => 'RETRY-CUSTOM-SKU', 'name' => 'Retry', 'tracking_mode' => 'QUANTITY', 'initial_quantity' => '10', 'role' => 'owner'];
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock-notes/products', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock-notes/products', $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock-notes/products', $payload)->assertStatus(409);
        $this->actingAs($owner);
        foreach (['-1', '0.0001', '100000000000'] as $invalid) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock-notes/products', ['name' => 'invalid', 'tracking_mode' => 'QUANTITY', 'initial_quantity' => $invalid])->assertUnprocessable();
        }
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock-notes/products', ['name' => 'invalid', 'tracking_mode' => 'AVAILABILITY_ONLY', 'initial_quantity' => 0])->assertUnprocessable();
    }

    public function test_no_op_actual_keeps_an_event_and_rollback_refuses_to_erase_new_history(): void
    {
        $this->owner();
        $product = $this->createNote(['initial_quantity' => 10]);
        $this->action($product, 'set_quantity', ['quantity' => 10, 'expected_version' => $product['stock_version'], 'reason' => 'Verified actual unchanged'])->assertCreated();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('product_events', ['action' => 'set_quantity', 'reason' => 'Verified actual unchanged', 'stock_movement_id' => null]);
        $migration = require database_path('migrations/0001_01_12_000000_add_smart_stock_notes.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse history loss');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Smart Stock data exists', $error->getMessage());
        }
        $this->assertDatabaseCount('product_events', 2);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_mode_conversion_retries_after_commit_replay_before_current_mode_validation(): void
    {
        $this->owner();
        $product = $this->createNote(['tracking_mode' => 'AVAILABILITY_ONLY']);
        $key = (string) Str::uuid();
        $path = '/api/stock-notes/products/'.$product['id'];
        $payload = ['tracking_mode' => 'QUANTITY', 'initial_quantity' => '7', 'expected_version' => $product['stock_version'], 'confirm_mode_change' => true, 'reason' => 'Counted seven'];
        $saved = $this->withHeader('Idempotency-Key', $key)->patchJson($path, $payload)->assertOk()->assertJsonPath('product.quantity', '7.000');
        $this->withHeader('Idempotency-Key', $key)->patchJson($path, $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('product.quantity', '7.000');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->patchJson($path, [...$payload, 'expected_version' => $saved->json('product.stock_version')])->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('product_events', 2);
    }

    public function test_literal_zero_sku_is_preserved_and_replays_as_a_custom_sku(): void
    {
        $this->owner();
        $key = (string) Str::uuid();
        $payload = ['name' => 'Zero SKU', 'tracking_mode' => 'QUANTITY', 'sku' => '0'];
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock-notes/products', $payload)->assertCreated()->assertJsonPath('product.sku', '0')->assertJsonPath('product.sku_is_generated', false);
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/stock-notes/products', $payload)->assertOk()->assertJsonPath('replayed', true);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/stock-notes/products', $payload)->assertUnprocessable();
    }
}
