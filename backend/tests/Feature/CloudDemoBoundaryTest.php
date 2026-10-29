<?php

namespace Tests\Feature;

use App\Services\DemoProvisioner;
use App\Support\DemoEnvironment;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CloudDemoBoundaryTest extends TestCase
{
    private function preview(): void
    {
        $this->app['env'] = 'production';
        config(['partshelf.demo_enabled' => true, 'partshelf.deployment_environment' => 'preview',
            'database.default' => 'pgsql', 'database.connections.pgsql.search_path' => 'partshelf_demo',
            'database.connections.pgsql.username' => 'partshelf_demo_runtime', 'database.connections.pgsql.url' => null]);
    }

    public function test_preview_demo_requires_the_dedicated_schema_and_runtime_role(): void
    {
        $this->preview();
        $this->assertTrue(DemoEnvironment::enabled());
        config(['database.connections.pgsql.search_path' => 'public']);
        $this->assertFalse(DemoEnvironment::enabled());
        config(['database.connections.pgsql.search_path' => 'partshelf_demo', 'database.connections.pgsql.username' => 'partshelf_runtime']);
        $this->assertFalse(DemoEnvironment::enabled());
    }

    public function test_production_target_and_missing_target_fail_closed(): void
    {
        $this->preview();
        foreach (['production', null, 'development'] as $target) {
            config(['partshelf.deployment_environment' => $target]);
            $this->assertFalse(DemoEnvironment::enabled());
        }
    }

    public function test_disabled_flag_stays_disabled_in_preview(): void
    {
        $this->preview();
        config(['partshelf.demo_enabled' => false]);
        $this->assertFalse(DemoEnvironment::enabled());
    }

    public function test_connection_url_cannot_override_cloud_demo_isolation(): void
    {
        $this->preview();
        config(['database.connections.pgsql.url' => 'postgresql://other_role:synthetic@other.neon.tech/neondb?search_path=public']);
        $this->assertFalse(DemoEnvironment::enabled());
    }

    public function test_provision_rejects_wrong_active_identity_before_writing(): void
    {
        $this->preview();
        $manager = DB::getFacadeRoot();
        foreach ([['role' => 'partshelf_runtime', 'schema' => 'partshelf_demo'], ['role' => 'partshelf_demo_runtime', 'schema' => 'public']] as $identity) {
            $connection = Mockery::mock(Connection::class);
            $connection->shouldReceive('getDatabaseName')->andReturn('neondb');
            $connection->shouldReceive('getDriverName')->andReturn('pgsql');
            foreach (['host' => 'ep-test.neon.tech', 'username' => 'partshelf_demo_runtime', 'search_path' => 'partshelf_demo'] as $key => $value) {
                $connection->shouldReceive('getConfig')->with($key)->andReturn($value);
            }
            $connection->shouldReceive('selectOne')->once()->andReturn((object) $identity);
            DB::shouldReceive('connection')->andReturn($connection);
            DB::shouldReceive('transaction')->never();
            try {
                app(DemoProvisioner::class)->provision([]);
                $this->fail('Unsafe active identity accepted');
            } catch (RuntimeException $e) {
                $this->assertSame('Cloud Demo connection identity rejected.', $e->getMessage());
            } finally {
                DB::swap($manager);
            }
        }
    }
}
