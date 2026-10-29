<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://127.0.0.1:5175');
    }

    public function test_demo_username_login_requires_the_actual_database_role(): void
    {
        config(['partshelf.demo_enabled' => true]);
        $user = User::factory()->create(['email' => 'owner.demo@partshelf.invalid', 'password' => 'Demo-Password-9273', 'role' => 'owner']);
        $user->forceFill(['username' => 'owner.demo'])->save();
        $this->postJson('/api/login', ['username' => 'owner.demo', 'password' => 'Demo-Password-9273', 'role' => 'staff'])->assertUnprocessable();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/login', ['username' => 'OWNER.DEMO', 'password' => 'Demo-Password-9273', 'role' => 'owner'])
            ->assertOk()->assertJsonPath('user.role', 'owner');
    }

    public function test_staff_cannot_select_owner_or_log_in_with_wrong_password(): void
    {
        config(['partshelf.demo_enabled' => true]);
        $user = User::factory()->create(['email' => 'staff.demo@partshelf.invalid', 'password' => 'Staff-Password-9273', 'role' => 'staff']);
        $user->forceFill(['username' => 'staff.demo'])->save();
        foreach ([['role' => 'owner', 'password' => 'Staff-Password-9273'], ['role' => 'staff', 'password' => 'incorrect'], ['password' => 'Staff-Password-9273']] as $input) {
            $this->postJson('/api/login', ['username' => 'staff.demo', ...$input])->assertUnprocessable();
            $this->getJson('/api/user')->assertUnauthorized();
        }
        $this->postJson('/api/login', ['username' => 'staff.demo', 'password' => 'Staff-Password-9273', 'role' => 'staff'])->assertOk();
        $this->postJson('/api/stock/corrections')->assertForbidden();
    }

    public function test_demo_username_login_is_disabled_by_default_and_metadata_has_no_password(): void
    {
        $this->postJson('/api/login', ['username' => 'owner.demo', 'password' => 'anything', 'role' => 'owner'])->assertUnprocessable();
        $this->getJson('/api/demo')->assertOk()->assertExactJson(['enabled' => false]);
        config(['partshelf.demo_enabled' => true]);
        $this->getJson('/api/demo')->assertOk()->assertJsonPath('enabled', true)->assertJsonMissingPath('password');
    }
}
