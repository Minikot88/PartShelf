<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockNotes
{
    public static function present(Product $product): array
    {
        $product->loadMissing(['inventoryBalance', 'category', 'storageLocation', 'fitments']);
        $quantity = $product->tracking_mode === 'QUANTITY' ? $product->inventoryBalance?->quantity : null;
        $status = $product->tracking_mode === 'AVAILABILITY_ONLY' ? $product->availability_status
            : ($quantity === null ? 'UNSET' : ((float) $quantity === 0.0 ? 'OUT_OF_STOCK' : ((float) $quantity <= (float) $product->min_stock_quantity ? 'LOW_STOCK' : 'IN_STOCK')));
        $data = $product->toArray();
        unset($data['inventory_balance'], $data['image_path']);

        return array_merge($data, ['quantity' => $quantity, 'stock_status' => $status, 'needs_reorder' => $product->reorder_needed || in_array($status, ['OUT_OF_STOCK', 'LOW_STOCK'], true)]);
    }

    public function mutate(Request $request, string $action, array $data, ?int $id = null): array
    {
        [$key, $fingerprint] = $this->identity($request, $action, $data, $id);

        return DB::transaction(function () use ($request, $action, $data, $id, $key, $fingerprint) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
            $existing = DB::table('product_events')->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Idempotency key was already used for a different request.');

                return [array_merge(json_decode($existing->response_data, true, 512, JSON_THROW_ON_ERROR), ['replayed' => true]), true];
            }
            abort_if(StockMovement::where('idempotency_key', $key)->exists() || DB::table('stock_count_corrections')->where('idempotency_key', $key)->exists(), 409, 'Idempotency key already used.');
            if (isset($data['sku']) && trim($data['sku']) !== '') {
                $sku = mb_strtolower(trim($data['sku']));
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['product-sku:'.$sku]);
                abort_if(Product::whereRaw('LOWER(sku) = ?', [$sku])->when($id, fn ($q) => $q->whereKeyNot($id))->exists(), 422, 'The SKU has already been taken.');
            }
            $before = null;
            $movement = null;
            if ($action === 'create') {
                $attributes = $this->metadata($data);
                $sku = trim((string) ($data['sku'] ?? ''));
                $attributes['sku'] = $sku === '' ? 'AUTO-'.Str::ulid() : $sku;
                $attributes['sku_is_generated'] = $sku === '';
                $attributes += ['base_unit' => 'ชิ้น', 'min_stock_quantity' => 0];
                $product = Product::create($attributes + ['tracking_mode' => $data['tracking_mode'], 'availability_status' => $data['availability_status'] ?? 'UNKNOWN']);
                if ($product->tracking_mode === 'QUANTITY' && isset($data['initial_quantity'])) {
                    $movement = $this->quantity($product, $request, 'set_quantity', $data['initial_quantity'], $data['reason'] ?? 'Initial quantity', $key, $fingerprint);
                }
            } else {
                $product = Product::query()->lockForUpdate()->findOrFail($id);
                abort_if($product->archived_at && $action !== 'restore', 409, 'Restore this product before changing it.');
                if (! in_array($action, ['sale', 'receipt'], true)) {
                    abort_unless((int) $data['expected_version'] === $product->stock_version, 409, 'Product changed. Refresh and review before saving.');
                }
                $before = self::present($product);
                switch ($action) {
                    case 'edit':
                        $next = $data['tracking_mode'] ?? $product->tracking_mode;
                        if (isset($data['initial_quantity'])) {
                            abort_unless($product->tracking_mode === 'AVAILABILITY_ONLY' && $next === 'QUANTITY', 422, 'Use set_quantity with a reason to change an existing quantity.');
                        }
                        if ($next !== $product->tracking_mode) {
                            abort_unless(($data['confirm_mode_change'] ?? false) && trim($data['reason'] ?? '') !== '', 422, 'Confirm the tracking mode change and provide a reason.');
                            if ($next === 'QUANTITY') {
                                abort_unless(isset($data['initial_quantity']), 422, 'An explicit quantity is required when returning to quantity tracking.');
                                $movement = $this->quantity($product, $request, 'set_quantity', $data['initial_quantity'], $data['reason'], $key, $fingerprint);
                            } else {
                                $product->availability_status = $data['availability_status'] ?? 'UNKNOWN';
                            }
                            $product->tracking_mode = $next;
                        }
                        if ($next === 'AVAILABILITY_ONLY' && isset($data['availability_status'])) {
                            $product->availability_status = $data['availability_status'];
                        }
                        $product->fill($this->metadata($data));
                        if (isset($data['sku']) && trim($data['sku']) !== '') {
                            $product->sku_is_generated = false;
                        }
                        break;
                    case 'sale':
                    case 'receipt':
                    case 'set_quantity':
                        abort_unless($product->tracking_mode === 'QUANTITY', 409, 'This product tracks availability only.');
                        $movement = $this->quantity($product, $request, $action, $data['quantity'], $data['reason'] ?? null, $key, $fingerprint);
                        break;
                    case 'availability':
                        abort_unless($product->tracking_mode === 'AVAILABILITY_ONLY', 409, 'This product tracks quantity.');
                        $product->availability_status = $data['availability_status'];
                        break;
                    case 'reorder':
                        $product->reorder_needed = $data['reorder_needed'];
                        $product->reorder_note = $data['reorder_note'] ?? null;
                        break;
                    case 'archive': $product->archived_at = now();
                        break;
                    case 'restore': $product->archived_at = null;
                        break;
                }
            }
            if (array_key_exists('fitments', $data)) {
                $product->fitments()->delete();
                foreach ($data['fitments'] as $fitment) {
                    $product->fitments()->create($fitment);
                }
            }
            if ($request->hasFile('image')) {
                $filename = $request->attributes->get('verified_image_key') ? basename($request->attributes->get('verified_image_key')) : 'notes-'.$key.'.'.$request->file('image')->extension();
                $product->image_path = $request->file('image')->storeAs('products', $filename, config('partshelf.image_disk'));
                abort_unless(is_string($product->image_path) && $product->image_path !== '', 500, 'Image storage failed; no product change was committed.');
            }
            $product->stock_version++;
            $product->status_updated_at = now();
            $product->status_updated_by = $request->user()->id;
            $product->save();
            $after = self::present($product->fresh());
            $response = ['product' => $after, 'replayed' => false];
            DB::table('product_events')->insert([
                'product_id' => $product->id, 'actor_id' => $request->user()->id, 'action' => $action,
                'idempotency_key' => $key, 'request_fingerprint' => $fingerprint,
                'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_data' => json_encode($after, JSON_THROW_ON_ERROR), 'response_data' => json_encode($response, JSON_THROW_ON_ERROR),
                'reason' => $data['reason'] ?? null, 'stock_movement_id' => $movement?->id, 'created_at' => now(),
            ]);

            return [$response, false];
        }, 3);
    }

    public function replayConsumedImage(Request $request, string $action, array $data, ?int $id = null): array
    {
        [$key, $fingerprint] = $this->identity($request, $action, $data, $id);

        return DB::transaction(function () use ($key, $fingerprint) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
            $existing = DB::table('product_events')->where('idempotency_key', $key)->first();
            // A consumed token permits only replay of its committed operation, never another write.
            abort_unless($existing, 422, 'Upload was already used; a new operation needs a new upload.');
            abort_unless(hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Idempotency key was already used for a different request.');

            return array_merge(json_decode($existing->response_data, true, 512, JSON_THROW_ON_ERROR), ['replayed' => true]);
        });
    }

    private function identity(Request $request, string $action, array $data, ?int $id): array
    {
        $key = $request->header('Idempotency-Key');
        abort_unless($key && Str::isUuid($key), 422, 'A valid Idempotency-Key UUID header is required.');
        // Actor and operation are part of the fingerprint: a key is never an authorization token.
        ksort($data);

        return [$key, hash('sha256', json_encode([$request->user()->id, $action, $id, $data], JSON_THROW_ON_ERROR))];
    }

    private function metadata(array $data): array
    {
        $result = array_intersect_key($data, array_flip(['sku', 'name', 'part_number', 'base_unit', 'category_id', 'storage_location_id', 'min_stock_quantity', 'notes']));
        foreach (['sku', 'name', 'part_number', 'base_unit', 'notes'] as $field) {
            if (isset($result[$field])) {
                $result[$field] = trim($result[$field]);
            }
        }

        return $result;
    }

    private function quantity(Product $product, Request $request, string $action, mixed $quantity, ?string $reason, string $key, string $fingerprint): ?StockMovement
    {
        $balance = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
        abort_if(! $balance && $action !== 'set_quantity', 409, 'Set the initial quantity before sales or receipts.');
        $before = $balance ? (int) round((float) $balance->quantity * 1000) : 0;
        $amount = (int) round((float) $quantity * 1000);
        $after = $action === 'set_quantity' ? $amount : $before + ($action === 'sale' ? -$amount : $amount);
        abort_if($after < 0, 409, 'Insufficient stock.');
        abort_if($after > 99999999999999, 422, 'The resulting quantity is too large.');
        $delta = $after - $before;
        $type = ! $balance ? 'opening' : ($action === 'set_quantity' ? 'correction' : $action);
        $movement = null;
        if ($delta !== 0 || ! $balance) {
            $movement = StockMovement::create(['product_id' => $product->id, 'movement_type' => $type,
                'quantity_delta' => number_format($delta / 1000, 3, '.', ''), 'reason' => $reason,
                'created_by_user_id' => $request->user()->id, 'occurred_at' => now(), 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint]);
        }
        InventoryBalance::updateOrCreate(['product_id' => $product->id], ['quantity' => number_format($after / 1000, 3, '.', ''), 'last_movement_at' => now()]);

        return $movement;
    }
}
