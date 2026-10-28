<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartshelfSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_domain_tables_exist_after_a_fresh_migration(): void
    {
        foreach ([
            'categories',
            'storage_locations',
            'products',
            'inventory_balances',
            'stock_movements',
            'stock_counts',
            'stock_count_items',
            'stock_count_attempts',
            'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }
    }

    public function test_sku_is_unique(): void
    {
        $location = $this->createLocation();
        $this->createProduct('PART-001', $location);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createProduct('PART-001', $location);
    }

    public function test_storage_location_path_is_unique(): void
    {
        $this->createLocation();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->createLocation();
    }

    public function test_foreign_keys_reject_unknown_product_references(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('inventory_balances')->insert([
            'product_id' => 999999,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_stock_balance_database_check_rejects_negative_quantities_on_postgresql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only CHECK constraint; covered by the local PostgreSQL acceptance run.');
        }

        $location = $this->createLocation();
        $productId = $this->createProduct('PART-002', $location);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('inventory_balances')->insert([
            'product_id' => $productId,
            'quantity' => -0.001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLocation(): int
    {
        return DB::table('storage_locations')->insertGetId([
            'zone' => 'A',
            'shelf' => '01',
            'level' => '01',
            'bin' => '01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(string $sku, int $locationId): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => 'Test part',
            'base_unit' => 'ชิ้น',
            'storage_location_id' => $locationId,
            'min_stock_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
