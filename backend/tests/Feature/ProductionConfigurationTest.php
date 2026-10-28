<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_production_configuration_fails_closed_without_disclosing_values(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);
        $this->getJson('/api/health')->assertStatus(503)
            ->assertExactJson(['message' => 'Service configuration is incomplete.'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_production_health_runs_with_explicit_shared_state_private_storage_and_secure_cookies(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://app.example.test',
            'partshelf.file_transport' => 'object', 'partshelf.image_disk' => 'r2',
            'session.secure' => true, 'session.http_only' => true, 'session.driver' => 'database',
            'cache.default' => 'database', 'filesystems.disks.r2.key' => 'local-test-only',
            'filesystems.disks.r2.secret' => 'local-test-only', 'filesystems.disks.r2.bucket' => 'test-only',
            'filesystems.disks.r2.endpoint' => 'https://storage.example.test',
        ]);
        $this->getJson('/api/health')->assertOk()->assertJsonPath('database', 'ok')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/user')->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
    }
}
