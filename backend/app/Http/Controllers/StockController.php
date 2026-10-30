<?php

namespace App\Http\Controllers;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockNotes;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $products = Product::query()->with('storageLocation')
            ->with('inventoryBalance')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('LOWER(sku) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])))
            ->orderBy('sku')->paginate(25)->through(function (Product $product): array {
                $note = StockNotes::present($product);
                $quantity = $note['quantity'];
                $minimum = (float) $product->min_stock_quantity;

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'stock_status' => ['OUT_OF_STOCK' => 'out', 'LOW_STOCK' => 'low', 'IN_STOCK' => 'ok', 'UNKNOWN' => 'unknown', 'UNSET' => 'unset'][$note['stock_status']],
                ];
            });

        return response()->json($products);
    }

    public function movements(Product $product): JsonResponse
    {
        return response()->json($product->stockMovements()->with('createdBy:id,name')->orderByDesc('occurred_at')->orderByDesc('id')->paginate(25));
    }

    public function opening(Request $request): JsonResponse
    {
        return $this->record($request, 'opening');
    }

    public function receipt(Request $request): JsonResponse
    {
        return $this->record($request, 'receipt');
    }

    private function record(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:'.($type === 'opening' ? '0' : '0.001'), 'max:99999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $key = $request->header('Idempotency-Key');
        if (! $key || ! Str::isUuid($key)) {
            return response()->json(['message' => 'A valid Idempotency-Key UUID header is required.'], 422);
        }

        $quantityMilli = (int) round((float) $validated['quantity'] * 1000);
        $reason = isset($validated['reason']) ? trim($validated['reason']) : null;
        $fingerprint = hash('sha256', json_encode([$type, (int) $validated['product_id'], $quantityMilli, $reason], JSON_THROW_ON_ERROR));

        try {
            [$movement, $balance, $replayed] = DB::transaction(function () use ($request, $type, $validated, $key, $fingerprint, $quantityMilli, $reason): array {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
                }
                abort_if(DB::table('product_events')->where('idempotency_key', $key)->exists(), 409, 'Idempotency key already used by Stock Notes.');
                $existing = StockMovement::query()->where('idempotency_key', $key)->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                        abort(409, 'Idempotency key was already used for a different request.');
                    }

                    return [$existing, InventoryBalance::query()->where('product_id', $existing->product_id)->firstOrFail(), true];
                }

                $product = Product::query()->lockForUpdate()->findOrFail($validated['product_id']);
                abort_if($product->archived_at || $product->tracking_mode !== 'QUANTITY', 409, 'Product is archived or tracks availability only.');
                $balance = InventoryBalance::query()->where('product_id', $product->id)->lockForUpdate()->first();
                abort_if(! $balance && $type !== 'opening', 409, 'Set the initial quantity before receiving stock.');
                $priorMovements = StockMovement::query()->where('product_id', $product->id)->exists();
                if ($type === 'opening' && ($priorMovements || $balance)) {
                    abort(409, 'Opening balance has already been set or stock movements exist.');
                }
                if (! $balance) {
                    $balance = InventoryBalance::query()->create(['product_id' => $product->id, 'quantity' => '0.000']);
                }
                $currentMilli = (int) round((float) $balance->quantity * 1000);
                $newMilli = $currentMilli + $quantityMilli;
                if ($newMilli > 99999999999999) {
                    abort(422, 'The resulting stock quantity is too large.');
                }
                $now = now();
                $movement = StockMovement::query()->create([
                    'product_id' => $product->id,
                    'movement_type' => $type,
                    'quantity_delta' => number_format($quantityMilli / 1000, 3, '.', ''),
                    'reason' => $reason,
                    'created_by_user_id' => $request->user()->id,
                    'occurred_at' => $now,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                ]);
                $balance->update(['quantity' => number_format($newMilli / 1000, 3, '.', ''), 'last_movement_at' => $now]);
                $product->increment('stock_version');
                $product->update(['status_updated_at' => $now, 'status_updated_by' => $request->user()->id]);
                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'stock.'.$type,
                    'entity_type' => 'stock_movement',
                    'entity_id' => $movement->id,
                    'before_data' => json_encode(['quantity' => number_format($currentMilli / 1000, 3, '.', '')], JSON_THROW_ON_ERROR),
                    'after_data' => json_encode(['quantity' => number_format($newMilli / 1000, 3, '.', ''), 'product_id' => $product->id], JSON_THROW_ON_ERROR),
                    'reason' => $reason,
                    'created_at' => $now,
                ]);

                return [$movement, $balance->fresh(), false];
            });
        } catch (QueryException $exception) {
            // The unique key remains a last line of defense on databases without advisory locks.
            $existing = StockMovement::query()->where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                abort(409, 'Idempotency key was already used for a different request.');
            }
            $movement = $existing;
            $balance = InventoryBalance::query()->where('product_id', $existing->product_id)->firstOrFail();
            $replayed = true;
        }

        return response()->json([
            'movement' => $movement,
            'balance' => $balance,
            'replayed' => $replayed,
        ], $replayed ? 200 : 201);
    }
}
