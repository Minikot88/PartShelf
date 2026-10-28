<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarizes_stock_and_limits_variance_details_by_role(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $normal = $this->createProduct('DASH-OK', 2, 5);
        $low = $this->createProduct('DASH-LOW', 3, 2);
        $out = $this->createProduct('DASH-OUT', 0, 1);
        DB::table('inventory_balances')->insert([
            ['product_id' => $normal, 'quantity' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $low, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $out, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $countId = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $itemId = DB::table('stock_count_items')->where('stock_count_id', $countId)->where('product_id', $normal)->value('id');
        $this->postJson("/api/stock-counts/{$countId}/items/{$itemId}/counts", ['counted_quantity' => 5])->assertCreated();

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('summary.product_count', 3)
            ->assertJsonPath('summary.total_on_hand', '9.000')
            ->assertJsonPath('summary.low_stock_count', 1)
            ->assertJsonPath('summary.out_of_stock_count', 1)
            ->assertJsonPath('recent_variances.0.variance', '-2.000')
            ->assertJsonPath('recent_counts.0.item_count', 3);

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('summary.product_count', 3)
            ->assertJsonPath('can_view_variances', false)
            ->assertJsonCount(0, 'recent_variances');
    }

    private function createProduct(string $sku, float $minimum, int $idSeed): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => $sku, 'base_unit' => 'ชิ้น', 'min_stock_quantity' => $minimum,
            'created_at' => now()->addSeconds($idSeed), 'updated_at' => now()->addSeconds($idSeed),
        ]);
    }
}
