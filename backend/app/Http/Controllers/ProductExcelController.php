<?php

namespace App\Http\Controllers;

use App\Services\ObjectFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProductExcelController extends Controller
{
    private const HEADERS = ['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name'];

    public function template(): BinaryFileResponse|JsonResponse
    {
        $path = $this->workbook(self::HEADERS, []);

        return $this->download($path, 'products-template.xlsx');
    }

    public function preview(Request $request): JsonResponse
    {
        if ($request->filled('upload_token')) {
            $request->validate(['upload_token' => ['required', 'uuid']]);

            return app(ObjectFiles::class)->consume($request, $request->input('upload_token'), 'workbook', function ($file) use ($request) {
                $request->files->set('file', $file);
                $request = Request::createFrom($request);
                $request->merge(['upload_token' => null]);
                $request->attributes->set('verified_workbook', true);

                return $this->preview($request);
            });
        }
        abort_if(config('partshelf.file_transport') === 'object' && ! $request->attributes->get('verified_workbook'), 422, 'Use a verified direct upload.');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        $rows = $this->readRows($request->file('file')->getRealPath());
        if (count($rows) > 5000) {
            abort(422, 'The workbook may contain at most 5,000 data rows.');
        }
        if ($rows === []) {
            abort(422, 'The workbook contains no product rows.');
        }

        $valid = [];
        $preview = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $number = $index + 2;
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $partNumber = trim((string) ($row['part_number'] ?? ''));
            $unit = trim((string) ($row['base_unit'] ?? ''));
            $minimum = trim((string) ($row['min_stock_quantity'] ?? ''));
            $category = trim((string) ($row['category_name'] ?? ''));
            $errors = [];
            $key = mb_strtolower($sku);

            if ($sku === '' || mb_strlen($sku) > 100) {
                $errors[] = 'SKU is required and must be at most 100 characters.';
            } elseif (isset($seen[$key]) || DB::table('products')->whereRaw('LOWER(sku) = ?', [$key])->exists()) {
                $errors[] = 'SKU already exists or is duplicated in this workbook.';
            }
            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = 'Name is required and must be at most 255 characters.';
            }
            if ($unit === '' || mb_strlen($unit) > 40) {
                $errors[] = 'Base unit is required and must be at most 40 characters.';
            }
            if (! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', $minimum) || (float) $minimum < 0) {
                $errors[] = 'Minimum stock must be a non-negative number with up to 3 decimals.';
            }
            if (mb_strlen($partNumber) > 120) {
                $errors[] = 'Part number must be at most 120 characters.';
            }
            if (mb_strlen($category) > 120) {
                $errors[] = 'Category name must be at most 120 characters.';
            }

            if ($sku !== '') {
                $seen[$key] = true;
            }
            $normalized = [
                'sku' => $sku,
                'name' => $name,
                'part_number' => $partNumber === '' ? null : $partNumber,
                'base_unit' => $unit,
                'min_stock_quantity' => $minimum,
                'category_name' => $category === '' ? null : $category,
            ];
            $preview[] = ['row' => $number, 'sku' => $sku, 'name' => $name, 'errors' => $errors];
            if ($errors === []) {
                $valid[] = $normalized;
            }
        }

        $token = (string) Str::uuid();
        Cache::put('partshelf:product-import:'.$token, [
            'user_id' => $request->user()->id,
            'rows' => $valid,
        ], now()->addMinutes(15));

        return response()->json([
            'token' => $token,
            'valid_count' => count($valid),
            'error_count' => count($preview) - count($valid),
            'rows' => $preview,
            'expires_in_minutes' => 15,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'uuid']]);

        return Cache::lock('partshelf:import-lock:'.$validated['token'], 120)->block(5, fn () => $this->confirmLocked($request, $validated['token']));
    }

    private function confirmLocked(Request $request, string $token): JsonResponse
    {
        $preview = Cache::get('partshelf:product-import:'.$token);
        if (! is_array($preview) || (int) $preview['user_id'] !== (int) $request->user()->id) {
            abort(422, 'Import preview is expired or unavailable. Upload the workbook again.');
        }
        if ($preview['rows'] === []) {
            abort(422, 'No valid product rows are available to import.');
        }
        Cache::forget('partshelf:product-import:'.$token);

        $imported = DB::transaction(function () use ($preview, $request): int {
            $count = 0;
            foreach ($preview['rows'] as $row) {
                if (DB::table('products')->whereRaw('LOWER(sku) = ?', [mb_strtolower($row['sku'])])->exists()) {
                    abort(409, 'A product SKU changed after preview. Upload and preview the workbook again.');
                }
                $categoryId = null;
                if ($row['category_name']) {
                    $category = DB::table('categories')->whereNull('parent_id')
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($row['category_name'])])->first();
                    $categoryId = $category?->id ?? DB::table('categories')->insertGetId([
                        'name' => $row['category_name'], 'parent_id' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $productId = DB::table('products')->insertGetId([
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'part_number' => $row['part_number'],
                    'base_unit' => $row['base_unit'],
                    'min_stock_quantity' => $row['min_stock_quantity'],
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'product.imported',
                    'entity_type' => 'product',
                    'entity_id' => $productId,
                    'before_data' => null,
                    'after_data' => json_encode(array_merge($row, ['category_id' => $categoryId]), JSON_THROW_ON_ERROR),
                    'reason' => 'Excel import',
                    'created_at' => now(),
                ]);
                $count++;
            }

            return $count;
        });

        return response()->json(['imported_count' => $imported], 201);
    }

    public function exportProducts(): BinaryFileResponse|JsonResponse
    {
        $rows = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->orderBy('p.sku')->get(['p.sku', 'p.name', 'p.part_number', 'p.base_unit', 'p.min_stock_quantity', 'c.name as category_name'])
            ->map(fn ($product) => [$product->sku, $product->name, $product->part_number, $product->base_unit, $product->min_stock_quantity, $product->category_name])
            ->all();
        $path = $this->workbook(self::HEADERS, $rows);

        return $this->download($path, 'products.xlsx');
    }

    public function exportCountResults(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate(['count_id' => ['sometimes', 'integer', 'exists:stock_counts,id']]);
        $query = DB::table('stock_count_items as i')
            ->join('stock_counts as c', 'c.id', '=', 'i.stock_count_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('stock_count_attempts as a', 'a.stock_count_item_id', '=', 'i.id')
            ->leftJoin('stock_count_corrections as cc', 'cc.stock_count_attempt_id', '=', 'a.id')
            ->when(isset($validated['count_id']), fn ($q) => $q->where('c.id', $validated['count_id']))
            ->orderBy('c.id')->orderBy('p.sku');
        $rows = $query->get([
            'c.id as count_id', 'c.status', 'p.sku', 'p.name', 'i.reference_quantity', 'i.reference_movement_id',
            'a.attempt_number', 'a.counted_quantity', 'a.movement_watermark_id', 'cc.quantity_delta as correction_delta', 'cc.reason as correction_reason',
            DB::raw('COALESCE((SELECT SUM(sm.quantity_delta) FROM stock_movements sm WHERE sm.product_id = i.product_id AND a.movement_watermark_id IS NOT NULL AND (i.reference_movement_id IS NULL OR sm.id > i.reference_movement_id) AND sm.id <= a.movement_watermark_id), 0) AS movement_delta'),
        ])
            ->map(fn ($item) => [
                $item->count_id,
                $item->status,
                $item->sku,
                $item->name,
                $item->reference_quantity,
                $item->attempt_number,
                $item->counted_quantity,
                $item->counted_quantity === null ? null : number_format((float) $item->reference_quantity + (float) $item->movement_delta, 3, '.', ''),
                $item->counted_quantity === null ? null : number_format((float) $item->counted_quantity - (float) $item->reference_quantity - (float) $item->movement_delta, 3, '.', ''),
                $item->correction_delta,
                $item->correction_reason,
            ])->all();
        $countId = $validated['count_id'] ?? 'all';
        $path = $this->workbook(['count_id', 'status', 'sku', 'name', 'reference_quantity', 'attempt_number', 'counted_quantity', 'comparison_reference_quantity', 'variance', 'correction_delta', 'correction_reason'], $rows);

        return $this->download($path, 'count-results-'.$countId.'.xlsx');
    }

    private function download(string $path, string $filename): BinaryFileResponse|JsonResponse
    {
        if (config('partshelf.file_transport') === 'object') {
            return app(ObjectFiles::class)->export($path, $filename);
        }

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    private function readRows(string $path): array
    {
        $zip = new \ZipArchive;
        abort_unless($zip->open($path) === true, 422, 'The workbook archive is invalid.');
        try {
            abort_if($zip->numFiles > 256, 422, 'The workbook contains too many archive entries.');
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $expanded += $entry['size'];
                abort_if($expanded > 64 * 1024 * 1024 || str_contains($entry['name'], '..') || str_starts_with($entry['name'], '/'), 422, 'The workbook archive is unsafe or too large.');
            }
        } finally {
            $zip->close();
        }
        $reader = new Reader;
        $reader->open($path);
        try {
            $sheets = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[] = $sheet;
                if (count($sheets) > 1) {
                    abort(422, 'Only single-sheet workbooks are supported.');
                }
            }
            if ($sheets === []) {
                abort(422, 'Workbook has no worksheets.');
            }
            $rawRows = [];
            foreach ($sheets[0]->getRowIterator() as $row) {
                $rawRows[] = array_map(fn ($cell) => $cell->getValue(), $row->cells);
                if (count($rawRows) > 5001) {
                    abort(422, 'The workbook may contain at most 5,000 data rows.');
                }
            }
            $headers = array_map(fn ($value) => mb_strtolower(trim((string) $value)), array_shift($rawRows) ?? []);
            if ($headers !== self::HEADERS) {
                abort(422, 'Workbook headers must match the provided product template.');
            }

            return array_values(array_filter(array_map(function (array $values): array {
                $values = array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), null);

                return array_combine(self::HEADERS, array_map(fn ($value) => $value === null ? '' : (string) $value, $values));
            }, $rawRows), fn (array $values) => array_filter($values, fn ($value) => trim((string) $value) !== '') !== []));
        } catch (Throwable $exception) {
            if ($exception instanceof HttpException) {
                throw $exception;
            }
            abort(422, 'The uploaded workbook could not be read.');
        } finally {
            $reader->close();
        }
    }

    private function workbook(array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'partshelf-xlsx-');
        if ($path === false) {
            abort(500, 'Could not create workbook.');
        }
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
