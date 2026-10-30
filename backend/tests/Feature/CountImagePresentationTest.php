<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CountImagePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_thumbnail_uses_authorized_image_route_without_exposing_private_key(): void
    {
        config(['partshelf.image_disk' => 'r2']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $product = DB::table('products')->insertGetId(['sku' => 'UX-IMAGE', 'name' => 'Count thumbnail', 'base_unit' => 'piece', 'min_stock_quantity' => 0, 'image_path' => 'products/private-test.png', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_balances')->insert(['product_id' => $product, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $count = $this->postJson('/api/stock-counts', [])->assertCreated()->json('count.id');
        $this->getJson('/api/stock-counts/'.$count)->assertOk()
            ->assertJsonPath('items.0.product.image_url', '/api/products/'.$product.'/image')
            ->assertJsonMissingPath('items.0.product.image_path');
    }
}
