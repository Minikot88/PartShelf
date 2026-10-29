<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private array $spaHeaders = [
        'Accept' => 'application/json',
        'Origin' => 'http://127.0.0.1:5175',
    ];

    public function test_login_returns_a_safe_user_profile_and_establishes_a_session(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'Correct-Horse-93',
            'role' => 'owner',
        ]);

        $loginResponse = $this->withHeaders($this->spaHeaders)
            ->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'Correct-Horse-93'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonMissingPath('user.password');

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false)->getValue();
        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->spaHeaders)
            ->withCookie(config('session.cookie'), $sessionCookie)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_credentials_return_a_generic_error(): void
    {
        User::factory()->create([
            'email' => 'staff@example.test',
            'password' => 'Correct-Horse-93',
            'role' => 'staff',
        ]);

        $this->withHeaders($this->spaHeaders)
            ->postJson('/api/login', ['email' => 'staff@example.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_passwords_over_the_bcrypt_byte_limit_are_rejected_generically(): void
    {
        $this->withHeaders($this->spaHeaders)
            ->postJson('/api/login', [
                'email' => 'missing@example.test',
                'password' => str_repeat('A', 73),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_authenticated_user_can_log_out_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-93']);

        $loginResponse = $this->withHeaders($this->spaHeaders)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'Correct-Horse-93'])
            ->assertOk();

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'), false)->getValue();
        $this->app['auth']->forgetGuards();

        $logoutResponse = $this->withHeaders($this->spaHeaders)
            ->withCookie(config('session.cookie'), $sessionCookie)
            ->postJson('/api/logout')
            ->assertNoContent();

        $invalidatedSessionCookie = $logoutResponse->getCookie(config('session.cookie'), false)->getValue();
        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->spaHeaders)
            ->withCookie(config('session.cookie'), $invalidatedSessionCookie)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_private_user_route_rejects_anonymous_requests(): void
    {
        $this->withHeaders($this->spaHeaders)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_anonymous_browser_navigation_returns_json_401_without_a_login_redirect(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get('/api/user')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertHeaderMissing('Location');
    }

    public function test_anonymous_api_request_without_accept_header_returns_401(): void
    {
        $this->get('/api/products')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_only_owner_and_manager_pass_the_stock_correction_guard(): void
    {
        foreach (['owner', 'manager'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->withHeaders($this->spaHeaders)
                ->postJson('/api/stock/corrections')
                ->assertStatus(501);
        }

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->withHeaders($this->spaHeaders)
            ->postJson('/api/stock/corrections')
            ->assertForbidden();
    }

    public function test_passwords_are_hashed_when_users_are_saved(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-93']);

        $this->assertNotSame('Correct-Horse-93', $user->password);
        $this->assertTrue(Hash::check('Correct-Horse-93', $user->password));
    }

    public function test_database_rejects_roles_outside_the_three_approved_values(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.test',
            'password' => Hash::make('Correct-Horse-93'),
            'role' => 'administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_initial_owner_can_be_created_with_a_hidden_confirmed_password(): void
    {
        $this->artisan('partshelf:user:create', [
            'name' => 'Initial Owner',
            'email' => 'initial-owner@example.test',
            'role' => 'owner',
        ])
            ->expectsQuestion('Password (12+ characters, mixed case and a number)', 'Passphrase-Horse-93')
            ->expectsQuestion('Confirm password', 'Passphrase-Horse-93')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'initial-owner@example.test')->firstOrFail();

        $this->assertSame('owner', $user->role->value);
        $this->assertTrue(Hash::check('Passphrase-Horse-93', $user->password));

        $audit = DB::table('audit_logs')
            ->where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->first();

        $this->assertSame('user.created', $audit->action);
        $this->assertStringNotContainsString('Passphrase-Horse-93', $audit->after_data);
        $this->assertStringNotContainsString('password', $audit->after_data);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeaders($this->spaHeaders)
                ->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->withHeaders($this->spaHeaders)
            ->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_default_database_seeder_does_not_create_demo_users(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_feature_tests_use_the_isolated_postgres_database(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('partshelf_test', config('database.connections.pgsql.database'));
    }
}
