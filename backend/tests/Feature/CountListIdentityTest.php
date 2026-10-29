<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CountListIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_rounds_by_one_user_keep_their_own_ids_in_the_picker(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $ids[] = DB::table('stock_counts')->insertGetId(['status' => 'open', 'started_by_user_id' => $user->id, 'started_at' => now()->addSeconds($i), 'created_at' => now(), 'updated_at' => now()]);
        }
        $response = $this->actingAs($user)->getJson('/api/stock-counts')->assertOk();
        $this->assertSame(array_reverse($ids), array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.started_by', $user->name)->assertJsonPath('data.0.item_count', 0);
    }
}
