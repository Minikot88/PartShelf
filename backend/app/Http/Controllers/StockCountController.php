<?php

namespace App\Http\Controllers;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockCountController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = DB::table('stock_counts as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.started_by_user_id')
            ->leftJoinSub(DB::table('stock_count_items')->select('stock_count_id', DB::raw('COUNT(*) AS item_count'))->groupBy('stock_count_id'), 'totals', fn ($join) => $join->on('totals.stock_count_id', '=', 'c.id'))
            ->orderByDesc('c.started_at')->paginate(20)
            ->through(fn ($count) => [
                'id' => $count->id,
                'status' => $count->status,
                'started_at' => $count->started_at,
                'completed_at' => $count->completed_at,
                'started_by' => $count->name,
                'item_count' => (int) ($count->item_count ?? 0),
            ]);

        return response()->json($counts);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'storage_location_id' => ['sometimes', 'nullable', 'integer', 'exists:storage_locations,id'],
        ]);
        $locationId = $validated['storage_location_id'] ?? null;

        $countId = DB::transaction(function () use ($request, $locationId): int {
            $products = DB::table('products')->when($locationId, fn ($query) => $query->where('storage_location_id', $locationId))
                ->orderBy('id')->lockForUpdate()->get(['id', 'storage_location_id']);
            if ($products->isEmpty()) {
                abort(422, 'No products are available for this count.');
            }

            $startedAt = now();
            $productIds = $products->pluck('id');
            $movementWatermarks = DB::table('stock_movements')->whereIn('product_id', $productIds)
                ->select('product_id', DB::raw('MAX(id) AS movement_id'))->groupBy('product_id')->pluck('movement_id', 'product_id');
            $countId = DB::table('stock_counts')->insertGetId([
                'status' => 'open',
                'started_by_user_id' => $request->user()->id,
                'started_at' => $startedAt,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
            $balances = DB::table('inventory_balances')->whereIn('product_id', $products->pluck('id'))->pluck('quantity', 'product_id');
            $items = $products->map(fn ($product) => [
                'stock_count_id' => $countId,
                'product_id' => $product->id,
                'storage_location_id' => $product->storage_location_id,
                'reference_quantity' => $balances[$product->id] ?? '0.000',
                'reference_at' => $startedAt,
                'reference_movement_id' => $movementWatermarks[$product->id] ?? null,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ])->all();
            DB::table('stock_count_items')->insert($items);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'stock_count.started',
                'entity_type' => 'stock_count',
                'entity_id' => $countId,
                'before_data' => null,
                'after_data' => json_encode(['storage_location_id' => $locationId, 'item_count' => count($items), 'reference_at' => $startedAt->toIso8601String()], JSON_THROW_ON_ERROR),
                'reason' => null,
                'created_at' => $startedAt,
            ]);

            return $countId;
        });

        return response()->json(['count' => $this->countSummary($countId)], 201);
    }

    public function show(Request $request, int $count): JsonResponse
    {
        $record = DB::table('stock_counts')->where('id', $count)->first();
        abort_if(! $record, 404);
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'count' => $this->countSummary($count),
            'items' => $this->items($count, $search),
        ]);
    }

    public function lookup(Request $request, int $count): JsonResponse
    {
        abort_unless(DB::table('stock_counts')->where('id', $count)->exists(), 404);
        $validated = $request->validate(['sku' => ['required', 'string', 'max:100']]);
        $code = trim($validated['sku']);
        $internalId = preg_match('/^PS-(\d{8,})$/i', $code, $matches) ? (int) $matches[1] : null;
        $item = DB::table('stock_count_items as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.stock_count_id', $count)
            ->where(fn ($query) => $internalId === null
                ? $query->whereRaw('LOWER(p.sku) = ?', [mb_strtolower($code)])
                : $query->where('p.id', $internalId))
            ->value('i.id');
        if (! $item) {
            return response()->json(['status' => 'not_found', 'message' => 'SKU was not found in this count.'], 404);
        }
        $itemData = collect($this->items($count))->firstWhere('id', (int) $item);

        return response()->json(['status' => $itemData['count_status'], 'item' => $itemData]);
    }

    public function record(Request $request, int $count, int $item, bool $recount = false): JsonResponse
    {
        $validated = $request->validate([
            'counted_quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        DB::transaction(function () use ($request, $count, $item, $validated, $recount): void {
            $countRow = DB::table('stock_counts')->where('id', $count)->lockForUpdate()->first();
            abort_if(! $countRow, 404);
            abort_if($countRow->status !== 'open', 409, 'This count is no longer open.');
            $countItem = DB::table('stock_count_items')->where('stock_count_id', $count)->where('id', $item)->lockForUpdate()->first();
            abort_if(! $countItem, 404);
            $attemptNumber = (int) DB::table('stock_count_attempts')->where('stock_count_item_id', $item)->max('attempt_number');
            if (($attemptNumber > 0) !== $recount) {
                abort(409, $recount ? 'The item must be counted before it can be recounted.' : 'This item has already been counted.');
            }

            $product = DB::table('products')->where('id', $countItem->product_id)->lockForUpdate()->first();
            abort_if(! $product, 404);
            $movementWatermark = DB::table('stock_movements')->where('product_id', $product->id)->max('id');
            $countedAt = now();
            DB::table('stock_count_attempts')->insert([
                'stock_count_item_id' => $item,
                'attempt_number' => $attemptNumber + 1,
                'counted_quantity' => $validated['counted_quantity'],
                'counted_by_user_id' => $request->user()->id,
                'counted_at' => $countedAt,
                'movement_watermark_id' => $movementWatermark,
                'created_at' => $countedAt,
                'updated_at' => $countedAt,
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => $recount ? 'stock_count.item_recounted' : 'stock_count.item_counted',
                'entity_type' => 'stock_count_item',
                'entity_id' => $item,
                'before_data' => null,
                'after_data' => json_encode(['attempt_number' => $attemptNumber + 1, 'counted_quantity' => $validated['counted_quantity'], 'reference_quantity' => $countItem->reference_quantity, 'counted_at' => $countedAt->toIso8601String()], JSON_THROW_ON_ERROR),
                'reason' => null,
                'created_at' => $countedAt,
            ]);
        });

        $itemData = collect($this->items($count))->firstWhere('id', $item);

        return response()->json(['item' => $itemData], 201);
    }

    public function correct(Request $request, int $count, int $item): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $key = $request->header('Idempotency-Key');
        if (! $key || ! Str::isUuid($key)) {
            return response()->json(['message' => 'A valid Idempotency-Key UUID header is required.'], 422);
        }
        $reason = trim($validated['reason']);

        [$correction, $replayed] = DB::transaction(function () use ($request, $count, $item, $key, $reason): array {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
            }
            $existing = DB::table('stock_count_corrections')->where('idempotency_key', $key)->first();
            $fingerprint = hash('sha256', json_encode(['count-correction', $count, $item, $reason], JSON_THROW_ON_ERROR));
            if ($existing) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    abort(409, 'Idempotency key was already used for a different correction.');
                }

                return [$existing, true];
            }

            $countRow = DB::table('stock_counts')->where('id', $count)->lockForUpdate()->first();
            abort_if(! $countRow, 404);
            $countItem = DB::table('stock_count_items')->where('stock_count_id', $count)->where('id', $item)->lockForUpdate()->first();
            abort_if(! $countItem, 404);
            $attempt = DB::table('stock_count_attempts')->where('stock_count_item_id', $item)
                ->orderByDesc('attempt_number')->lockForUpdate()->first();
            abort_if(! $attempt, 409, 'The item must be counted before correction.');
            $correctionExists = DB::table('stock_count_corrections')->where('stock_count_attempt_id', $attempt->id)->exists();
            abort_if($correctionExists, 409, 'The latest attempt has already been corrected. Recount before another correction.');

            $product = Product::query()->lockForUpdate()->findOrFail($countItem->product_id);
            $balance = InventoryBalance::query()->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $balance) {
                $balance = InventoryBalance::query()->create(['product_id' => $product->id, 'quantity' => '0.000']);
            }
            $movementDelta = $attempt->movement_watermark_id === null ? 0.0 : (float) DB::table('stock_movements')->where('product_id', $product->id)
                ->when($countItem->reference_movement_id !== null, fn ($query) => $query->where('id', '>', $countItem->reference_movement_id))
                ->where('id', '<=', $attempt->movement_watermark_id)->sum('quantity_delta');
            $referenceMilli = (int) round(((float) $countItem->reference_quantity + $movementDelta) * 1000);
            $countedMilli = (int) round((float) $attempt->counted_quantity * 1000);
            $varianceMilli = $countedMilli - $referenceMilli;
            $beforeMilli = (int) round((float) $balance->quantity * 1000);
            $afterMilli = $beforeMilli + $varianceMilli;
            if ($afterMilli < 0 || $afterMilli > 99999999999999) {
                abort(409, 'Applying this variance would make the current balance invalid. Review movements and recount.');
            }

            $now = now();
            $movement = null;
            if ($varianceMilli !== 0) {
                $movement = StockMovement::query()->create([
                    'product_id' => $product->id,
                    'movement_type' => 'correction',
                    'quantity_delta' => number_format($varianceMilli / 1000, 3, '.', ''),
                    'reason' => $reason,
                    'created_by_user_id' => $request->user()->id,
                    'occurred_at' => $now,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                ]);
                $balance->update(['quantity' => number_format($afterMilli / 1000, 3, '.', ''), 'last_movement_at' => $now]);
            }

            $correctionId = DB::table('stock_count_corrections')->insertGetId([
                'stock_count_attempt_id' => $attempt->id,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'quantity_delta' => number_format($varianceMilli / 1000, 3, '.', ''),
                'balance_before' => number_format($beforeMilli / 1000, 3, '.', ''),
                'balance_after' => number_format($afterMilli / 1000, 3, '.', ''),
                'reason' => $reason,
                'stock_movement_id' => $movement?->id,
                'created_by_user_id' => $request->user()->id,
                'created_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'stock_count.corrected',
                'entity_type' => 'stock_count_attempt',
                'entity_id' => $attempt->id,
                'before_data' => json_encode(['balance' => number_format($beforeMilli / 1000, 3, '.', '')], JSON_THROW_ON_ERROR),
                'after_data' => json_encode(['variance' => number_format($varianceMilli / 1000, 3, '.', ''), 'balance' => number_format($afterMilli / 1000, 3, '.', ''), 'correction_id' => $correctionId, 'stock_movement_id' => $movement?->id], JSON_THROW_ON_ERROR),
                'reason' => $reason,
                'created_at' => $now,
            ]);

            return [(object) [
                'id' => $correctionId,
                'stock_count_attempt_id' => $attempt->id,
                'quantity_delta' => number_format($varianceMilli / 1000, 3, '.', ''),
                'balance_before' => number_format($beforeMilli / 1000, 3, '.', ''),
                'balance_after' => number_format($afterMilli / 1000, 3, '.', ''),
                'reason' => $reason,
                'stock_movement_id' => $movement?->id,
            ], false];
        });

        return response()->json([
            'correction' => [
                'id' => (int) $correction->id,
                'attempt_id' => (int) $correction->stock_count_attempt_id,
                'variance' => $correction->quantity_delta,
                'balance_before' => $correction->balance_before,
                'balance_after' => $correction->balance_after,
                'reason' => $correction->reason,
                'stock_movement_id' => $correction->stock_movement_id === null ? null : (int) $correction->stock_movement_id,
            ],
            'replayed' => $replayed,
        ], $replayed ? 200 : 201);
    }

    private function countSummary(int $count): array
    {
        $row = DB::table('stock_counts as c')->leftJoin('users as u', 'u.id', '=', 'c.started_by_user_id')
            ->where('c.id', $count)->first(['c.*', 'u.name as started_by']);
        abort_if(! $row, 404);

        return [
            'id' => (int) $row->id,
            'status' => $row->status,
            'started_at' => $row->started_at,
            'completed_at' => $row->completed_at,
            'started_by' => $row->started_by,
            'item_count' => (int) DB::table('stock_count_items')->where('stock_count_id', $count)->count(),
        ];
    }

    private function items(int $count, string $search = ''): array
    {
        $latest = DB::table('stock_count_attempts')->select('stock_count_item_id', DB::raw('MAX(attempt_number) AS latest_attempt'))->groupBy('stock_count_item_id');

        $rows = DB::table('stock_count_items as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('storage_locations as l', 'l.id', '=', 'i.storage_location_id')
            ->leftJoinSub($latest, 'la', fn ($join) => $join->on('la.stock_count_item_id', '=', 'i.id'))
            ->leftJoin('stock_count_attempts as a', fn ($join) => $join->on('a.stock_count_item_id', '=', 'i.id')->on('a.attempt_number', '=', 'la.latest_attempt'))
            ->leftJoin('stock_count_corrections as cc', 'cc.stock_count_attempt_id', '=', 'a.id')
            ->where('i.stock_count_id', $count)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereRaw('LOWER(p.sku) LIKE ?', ['%'.mb_strtolower($search).'%'])->orWhereRaw('LOWER(p.name) LIKE ?', ['%'.mb_strtolower($search).'%'])))
            ->orderBy('p.sku')->get([
                'i.id', 'i.product_id', 'p.sku', 'p.name', 'p.base_unit', 'i.storage_location_id',
                'l.zone', 'l.shelf', 'l.level', 'l.bin', 'i.reference_quantity', 'i.reference_at',
                'a.id as latest_attempt_id', 'a.attempt_number', 'a.counted_quantity', 'a.counted_at', 'a.counted_by_user_id',
                'cc.id as correction_id', 'cc.quantity_delta as correction_delta', 'cc.reason as correction_reason',
                DB::raw('COALESCE((SELECT SUM(sm.quantity_delta) FROM stock_movements sm WHERE sm.product_id = i.product_id AND a.movement_watermark_id IS NOT NULL AND (i.reference_movement_id IS NULL OR sm.id > i.reference_movement_id) AND sm.id <= a.movement_watermark_id), 0) AS movement_delta'),
            ]);
        $attempts = DB::table('stock_count_attempts as a')->join('stock_count_items as i', 'i.id', '=', 'a.stock_count_item_id')
            ->leftJoin('stock_count_corrections as cc', 'cc.stock_count_attempt_id', '=', 'a.id')
            ->where('i.stock_count_id', $count)->orderBy('a.attempt_number')->get([
                'a.stock_count_item_id', 'a.attempt_number', 'a.counted_quantity', 'a.counted_at', 'a.counted_by_user_id',
                'cc.id as correction_id', 'cc.quantity_delta as correction_delta', 'cc.reason as correction_reason',
                DB::raw('COALESCE((SELECT SUM(sm.quantity_delta) FROM stock_movements sm WHERE sm.product_id = i.product_id AND a.movement_watermark_id IS NOT NULL AND (i.reference_movement_id IS NULL OR sm.id > i.reference_movement_id) AND sm.id <= a.movement_watermark_id), 0) AS movement_delta'),
            ])->groupBy('stock_count_item_id');

        return $rows->map(function ($item) use ($attempts): array {
            $history = $attempts->get($item->id, collect())->map(fn ($attempt) => $this->attemptData($attempt, $item->reference_quantity))->values()->all();

            return [
                'id' => (int) $item->id,
                'product' => ['id' => (int) $item->product_id, 'sku' => $item->sku, 'name' => $item->name, 'base_unit' => $item->base_unit],
                'storage_location_id' => $item->storage_location_id === null ? null : (int) $item->storage_location_id,
                'location' => $item->zone === null ? null : implode('-', [$item->zone, $item->shelf, $item->level, $item->bin]),
                'reference_quantity' => $item->reference_quantity,
                'reference_at' => $item->reference_at,
                'attempt_number' => $item->attempt_number === null ? null : (int) $item->attempt_number,
                'counted_quantity' => $item->counted_quantity,
                'counted_at' => $item->counted_at,
                'count_status' => $item->counted_quantity === null ? 'not_counted' : ((float) $item->counted_quantity === 0.0 ? 'counted_zero' : 'counted'),
                'variance' => $history === [] ? null : $history[array_key_last($history)]['variance'],
                'attempts' => $history,
                'correction' => $item->correction_id === null ? null : [
                    'delta' => $item->correction_delta,
                    'reason' => $item->correction_reason,
                ],
            ];
        })->all();
    }

    private function attemptData(object $attempt, string $referenceQuantity): array
    {
        $referenceMilli = (int) round(((float) $referenceQuantity + (float) $attempt->movement_delta) * 1000);
        $countedMilli = (int) round((float) $attempt->counted_quantity * 1000);

        return [
            'attempt_number' => (int) $attempt->attempt_number,
            'counted_quantity' => $attempt->counted_quantity,
            'counted_at' => $attempt->counted_at,
            'counted_by_user_id' => $attempt->counted_by_user_id === null ? null : (int) $attempt->counted_by_user_id,
            'comparison_reference_quantity' => number_format($referenceMilli / 1000, 3, '.', ''),
            'variance' => number_format(($countedMilli - $referenceMilli) / 1000, 3, '.', ''),
            'correction' => $attempt->correction_id === null ? null : [
                'delta' => $attempt->correction_delta,
                'reason' => $attempt->correction_reason,
            ],
        ];
    }
}
