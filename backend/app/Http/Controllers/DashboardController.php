<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $totals = DB::table('products as p')
            ->leftJoin('inventory_balances as b', 'b.product_id', '=', 'p.id')
            ->whereNull('p.archived_at')
            ->selectRaw('COUNT(p.id) AS product_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN p.tracking_mode = 'QUANTITY' THEN b.quantity END), 0) AS total_on_hand")
            ->selectRaw("SUM(CASE WHEN p.tracking_mode = 'QUANTITY' AND b.quantity > 0 AND b.quantity <= p.min_stock_quantity THEN 1 ELSE 0 END) AS low_stock_count")
            ->selectRaw("SUM(CASE WHEN (p.tracking_mode = 'QUANTITY' AND b.quantity = 0) OR (p.tracking_mode = 'AVAILABILITY_ONLY' AND p.availability_status = 'OUT_OF_STOCK') THEN 1 ELSE 0 END) AS out_of_stock_count")
            ->selectRaw("SUM(CASE WHEN p.tracking_mode = 'QUANTITY' AND b.quantity IS NULL THEN 1 ELSE 0 END) AS unset_quantity_count")
            ->selectRaw("SUM(CASE WHEN p.tracking_mode = 'AVAILABILITY_ONLY' AND p.availability_status = 'UNKNOWN' THEN 1 ELSE 0 END) AS unknown_availability_count")
            ->first();

        $recentCounts = DB::table('stock_counts as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.started_by_user_id')
            ->leftJoinSub(DB::table('stock_count_items')->select('stock_count_id', DB::raw('COUNT(*) AS item_count'))->groupBy('stock_count_id'), 'totals', fn ($join) => $join->on('totals.stock_count_id', '=', 'c.id'))
            ->orderByDesc('c.started_at')->orderByDesc('c.id')->limit(5)
            ->get(['c.id', 'c.status', 'c.started_at', 'c.completed_at', 'u.name as started_by', 'totals.item_count'])
            ->map(fn ($count): array => [
                'id' => (int) $count->id,
                'status' => $count->status,
                'started_at' => $count->started_at,
                'completed_at' => $count->completed_at,
                'started_by' => $count->started_by,
                'item_count' => (int) ($count->item_count ?? 0),
            ]);

        $canViewVariances = $request->user()->canManageStock();
        $recentVariances = [];
        if ($canViewVariances) {
            $attempts = DB::table('stock_count_attempts as a')
                ->join('stock_count_items as i', 'i.id', '=', 'a.stock_count_item_id')
                ->join('products as p', 'p.id', '=', 'i.product_id')
                ->join('stock_counts as c', 'c.id', '=', 'i.stock_count_id')
                ->orderByDesc('a.counted_at')->orderByDesc('a.id')->limit(5)
                ->get(['a.id', 'a.attempt_number', 'a.counted_quantity', 'a.counted_at', 'a.movement_watermark_id', 'i.reference_quantity', 'i.reference_movement_id', 'i.product_id', 'i.stock_count_id', 'p.sku', 'p.name']);

            $recentVariances = $attempts->map(function ($attempt): array {
                $movementMilli = (int) round((float) DB::table('stock_movements')
                    ->where('product_id', $attempt->product_id)
                    ->where('id', '>', $attempt->reference_movement_id ?? 0)
                    ->where('id', '<=', $attempt->movement_watermark_id ?? 0)
                    ->sum('quantity_delta') * 1000);
                $varianceMilli = (int) round(((float) $attempt->counted_quantity - (float) $attempt->reference_quantity) * 1000) - $movementMilli;

                return [
                    'count_id' => (int) $attempt->stock_count_id,
                    'sku' => $attempt->sku,
                    'name' => $attempt->name,
                    'attempt_number' => (int) $attempt->attempt_number,
                    'counted_at' => $attempt->counted_at,
                    'variance' => number_format($varianceMilli / 1000, 3, '.', ''),
                ];
            })->all();
        }

        return response()->json([
            'summary' => [
                'product_count' => (int) $totals->product_count,
                'total_on_hand' => number_format((float) $totals->total_on_hand, 3, '.', ''),
                'low_stock_count' => (int) $totals->low_stock_count,
                'out_of_stock_count' => (int) $totals->out_of_stock_count,
                'unset_quantity_count' => (int) $totals->unset_quantity_count,
                'unknown_availability_count' => (int) $totals->unknown_availability_count,
            ],
            'recent_counts' => $recentCounts->values(),
            'recent_variances' => $recentVariances,
            'can_view_variances' => $canViewVariances,
        ]);
    }
}
