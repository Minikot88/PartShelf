<?php

namespace Tests\Feature;

use App\Services\DemoProvisioner;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DemoProvisionTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): array
    {
        return array_map(fn ($role) => ['username' => $role.'.demo', 'role' => $role, 'password' => 'Synthetic-Test-9273-'.$role], ['owner', 'manager', 'staff']);
    }

    public function test_seed_is_idempotent_keeps_passwords_hashed_and_does_not_reset_user_stock(): void
    {
        config(['partshelf.demo_enabled' => true]);
        $service = app(DemoProvisioner::class);
        $this->assertTrue($service->provision($this->accounts())['seeded']);
        $this->assertDatabaseCount('products', 25);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('stock_count_attempts', 1);
        foreach ($this->accounts() as $account) {
            $this->assertTrue(Hash::check($account['password'], DB::table('users')->where('username', $account['username'])->value('password')));
        }
        $product = DB::table('products')->where('sku', 'DEMO-003')->value('id');
        DB::table('inventory_balances')->where('product_id', $product)->update(['quantity' => 30]);
        $this->assertFalse($service->provision($this->accounts())['seeded']);
        $this->assertDatabaseCount('products', 25);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('30.000', DB::table('inventory_balances')->where('product_id', $product)->value('quantity'));
    }

    public function test_provision_refuses_production_even_when_the_flag_is_enabled(): void
    {
        config(['partshelf.demo_enabled' => true]);
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        app(DemoProvisioner::class)->provision($this->accounts());
    }

    public function test_provision_refuses_disabled_flag_before_any_account_write(): void
    {
        $this->expectException(RuntimeException::class);
        app(DemoProvisioner::class)->provision($this->accounts());
    }

    public function test_provision_checks_active_connection_host_before_any_transaction(): void
    {
        config(['partshelf.demo_enabled' => true, 'database.connections.pgsql.host' => 'demo-db']);
        $this->app['env'] = 'local';
        $manager = DB::getFacadeRoot();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn('partshelf_demo');
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('getConfig')->with('host')->andReturn('remote.example.invalid');
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('transaction')->never();
        try {
            $this->expectException(RuntimeException::class);
            app(DemoProvisioner::class)->provision($this->accounts());
        } finally {
            DB::swap($manager);
        }
    }
}
