<?php

namespace App\Services;

use App\Models\User;
use App\Support\DemoEnvironment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoProvisioner
{
    public function provision(array $accounts): array
    {
        $connection = DB::connection();
        $safeDemo = app()->environment('local') && $connection->getDatabaseName() === 'partshelf_demo' && $connection->getConfig('host') === 'demo-db';
        $safeTest = app()->environment('testing') && $connection->getDatabaseName() === 'partshelf_test';
        $safeCloud = DemoEnvironment::cloudPreview()
            && $connection->getDatabaseName() === 'neondb'
            && $connection->getConfig('username') === 'partshelf_demo_runtime'
            && $connection->getConfig('search_path') === 'partshelf_demo'
            && preg_match('/^[a-z0-9.-]+\.neon\.tech$/', $connection->getConfig('host') ?? '');
        if (! config('partshelf.demo_enabled') || $connection->getDriverName() !== 'pgsql' || (! $safeDemo && ! $safeTest && ! $safeCloud)) {
            throw new RuntimeException('Demo provisioning requires the isolated local demo database.');
        }

        if ($safeCloud) {
            $identity = $connection->selectOne('select current_user as role, current_schema() as schema');
            if ($identity->role !== 'partshelf_demo_runtime' || $identity->schema !== 'partshelf_demo') {
                throw new RuntimeException('Cloud Demo connection identity rejected.');
            }
        }
        if (count($accounts) !== 3 || count(array_unique(array_column($accounts, 'password'))) !== 3) {
            throw new RuntimeException('Exactly three distinct private demo passwords are required.');
        }

        return DB::transaction(function () use ($accounts): array {
            DB::select("SELECT pg_advisory_xact_lock(hashtext('partshelf-demo-seed-v1'))");
            $owners = [];
            foreach (['owner', 'manager', 'staff'] as $role) {
                $rows = array_values(array_filter($accounts, fn ($entry) => ($entry['role'] ?? null) === $role && ($entry['username'] ?? null) === $role.'.demo'));
                if (count($rows) !== 1 || ! preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{16,72}$/', $rows[0]['password'])) {
                    throw new RuntimeException('Invalid private demo account input.');
                }
                $entry = $rows[0];
                $email = $role.'.demo@partshelf.invalid';
                $user = User::query()->where('username', $entry['username'])->orWhere('email', $email)->first();
                if ($user && ($user->username !== $entry['username'] || $user->email !== $email || $user->role->value !== $role || ! Hash::check($entry['password'], $user->password))) {
                    throw new RuntimeException('Existing account does not match private demo ownership; no changes made.');
                }
                if (! $user) {
                    $user = new User;
                    $user->forceFill(['username' => $entry['username'], 'email' => $email, 'name' => 'Demo '.ucfirst($role), 'role' => $role, 'password' => $entry['password']])->save();
                }
                $owners[$role] = $user->id;
            }
            $marker = DB::table('audit_logs')->where('action', 'demo.seed.initialized')->where('reason', 'PartShelf demo seed v1')->exists();
            if ($marker) {
                return ['seeded' => false, 'accounts' => 3, 'products' => DB::table('products')->where('sku', 'like', 'DEMO-%')->count()];
            }
            if (DB::table('products')->where('sku', 'like', 'DEMO-%')->exists()) {
                throw new RuntimeException('Demo SKU collision; no existing records overwritten.');
            }
            $now = now();
            $categories = [];
            foreach (['เบรก', 'เครื่องยนต์', 'ช่วงล่าง', 'ไฟฟ้า'] as $name) {
                $categories[] = DB::table('categories')->insertGetId(['name' => 'DEMO · '.$name, 'parent_id' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
            $locations = [];
            foreach (['A', 'B', 'C'] as $zone) {
                $locations[] = DB::table('storage_locations')->insertGetId(['zone' => $zone, 'shelf' => '01', 'level' => '01', 'bin' => '01', 'created_at' => $now, 'updated_at' => $now]);
            }
            $names = ['ผ้าเบรกหน้า', 'ผ้าเบรกหลัง', 'จานเบรก', 'น้ำมันเบรก', 'กรองน้ำมันเครื่อง', 'กรองอากาศ', 'หัวเทียน', 'สายพาน', 'ปั๊มน้ำ', 'หม้อน้ำ', 'โช้คหน้า', 'โช้คหลัง', 'ลูกหมาก', 'บูชปีกนก', 'ยางกันโคลง', 'ลูกปืนล้อ', 'แบตเตอรี่', 'หลอดไฟหน้า', 'ฟิวส์', 'รีเลย์', 'ไดชาร์จ', 'ไดสตาร์ท', 'เซ็นเซอร์', 'คอยล์จุดระเบิด', 'ใบปัดน้ำฝน'];
            $firstProduct = null;
            foreach ($names as $index => $name) {
                $quantity = $index === 0 ? 0 : ($index === 1 ? 2 : 20 + $index);
                $product = DB::table('products')->insertGetId(['sku' => sprintf('DEMO-%03d', $index + 1), 'name' => $name.' (Demo)', 'part_number' => sprintf('PS-DEMO-%03d', $index + 1), 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 5, 'category_id' => $categories[$index % 4], 'storage_location_id' => $locations[$index % 3], 'created_at' => $now, 'updated_at' => $now]);
                DB::table('inventory_balances')->insert(['product_id' => $product, 'quantity' => $quantity, 'last_movement_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                if ($quantity > 0) {
                    DB::table('stock_movements')->insert(['product_id' => $product, 'movement_type' => 'opening', 'quantity_delta' => $quantity, 'reason' => 'PartShelf demo seed v1', 'created_by_user_id' => $owners['owner'], 'occurred_at' => $now, 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'demo-opening-'.$product), 'created_at' => $now, 'updated_at' => $now]);
                }
                if ($index === 2) {
                    $firstProduct = $product;
                }
            }
            $watermark = DB::table('stock_movements')->where('product_id', $firstProduct)->max('id');
            $count = DB::table('stock_counts')->insertGetId(['status' => 'open', 'started_by_user_id' => $owners['owner'], 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $item = DB::table('stock_count_items')->insertGetId(['stock_count_id' => $count, 'product_id' => $firstProduct, 'storage_location_id' => $locations[2], 'reference_quantity' => 22, 'reference_at' => $now, 'reference_movement_id' => $watermark, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('stock_count_attempts')->insert(['stock_count_item_id' => $item, 'attempt_number' => 1, 'counted_quantity' => 21, 'movement_watermark_id' => $watermark, 'counted_by_user_id' => $owners['staff'], 'counted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('audit_logs')->insert(['user_id' => $owners['owner'], 'action' => 'demo.seed.initialized', 'entity_type' => 'demo_seed', 'entity_id' => $count, 'after_data' => json_encode(['products' => 25, 'categories' => 4, 'locations' => 3, 'sample_count' => $count]), 'reason' => 'PartShelf demo seed v1', 'created_at' => $now]);

            return ['seeded' => true, 'accounts' => 3, 'products' => 25];
        });
    }
}
