<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ObjectFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $products = Product::query()->with(['category', 'fitments', 'storageLocation'])->when($search !== '', function ($query) use ($search): void {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($query) use ($term): void {
                foreach (['sku', 'name', 'part_number'] as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ?", [$term]);
                }

                $query->orWhereHas('fitments', function ($fitments) use ($term): void {
                    foreach (['make', 'model', 'engine'] as $column) {
                        $fitments->orWhereRaw("LOWER({$column}) LIKE ?", [$term]);
                    }
                });

                if (is_numeric($term = trim($term, '%'))) {
                    $query->orWhereHas('fitments', fn ($fitments) => $fitments->where('year', (int) $term));
                }
            });
        })->orderBy('sku')->paginate(25)->withQueryString();

        return response()->json($products);
    }

    public function resolveCode(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        if (! preg_match('/^PS-(\d{8,})$/i', trim($validated['code']), $matches)) {
            return response()->json(['message' => 'Product code was not found.'], 404);
        }
        $product = Product::query()->with(['category', 'fitments', 'storageLocation'])
            ->find((int) $matches[1]);
        if (! $product) {
            return response()->json(['message' => 'Product code was not found.'], 404);
        }

        return response()->json(['product' => $product]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => $product->load(['category', 'fitments', 'storageLocation'])]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('image_token')) {
            $request->validate(['image_token' => ['required', 'uuid']]);

            return app(ObjectFiles::class)->consume($request, $request->input('image_token'), 'image', function ($file, $row) use ($request) {
                $request->files->set('image', $file);
                $request = Request::createFrom($request);
                $request->merge(['image_token' => null]);
                $request->attributes->set('verified_image_key', $row->final_key);

                return $this->store($request);
            });
        }
        $validated = $this->validated($request);
        $imagePath = $this->storeImage($request);

        try {
            $product = DB::transaction(function () use ($request, $validated, $imagePath): Product {
                $product = Product::create($this->attributes($validated, $imagePath));
                $this->syncFitments($product, $validated['fitments'] ?? []);
                $this->audit($request, 'product.created', $product, null);

                return $product->load(['category', 'fitments', 'storageLocation']);
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk(config('partshelf.image_disk'))->delete($imagePath);
            }

            throw $exception;
        }

        return response()->json(['product' => $product], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($request->filled('image_token')) {
            $request->validate(['image_token' => ['required', 'uuid']]);

            return app(ObjectFiles::class)->consume($request, $request->input('image_token'), 'image', function ($file, $row) use ($request, $product) {
                $request->files->set('image', $file);
                $request = Request::createFrom($request);
                $request->merge(['image_token' => null]);
                $request->attributes->set('verified_image_key', $row->final_key);

                return $this->update($request, $product);
            });
        }
        $validated = $this->validated($request, $product);
        $before = $this->snapshot($product);
        $oldImagePath = $product->image_path;
        $newImagePath = $this->storeImage($request);

        try {
            $updated = DB::transaction(function () use ($request, $product, $validated, $before, $newImagePath): Product {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                abort_if($product->archived_at, 409, 'Restore this product before editing.');
                $product->update($this->attributes($validated, $newImagePath ?? $product->image_path, $product));

                if (array_key_exists('fitments', $validated)) {
                    $this->syncFitments($product, $validated['fitments']);
                }

                $this->audit($request, 'product.updated', $product, $before);
                $product->increment('stock_version');

                return $product->fresh()->load(['category', 'fitments', 'storageLocation']);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk(config('partshelf.image_disk'))->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath && config('partshelf.image_disk') !== 'r2') {
            // Object-mode images are removed by the transfer ledger cleanup after commit/grace.
            try {
                Storage::disk(config('partshelf.image_disk'))->delete($oldImagePath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json(['product' => $updated]);
    }

    public function assignLocation(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'storage_location_id' => ['present', 'nullable', 'integer', 'exists:storage_locations,id'],
        ]);
        $before = $product->storage_location_id;
        $locationId = $validated['storage_location_id'] ?? null;

        if ($before !== $locationId) {
            DB::transaction(function () use ($request, $product, $before, $locationId): void {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                abort_if($product->archived_at, 409, 'Restore this product before editing.');
                $product->update(['storage_location_id' => $locationId]);
                $product->increment('stock_version');
                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()->id,
                    'action' => 'product.location_changed',
                    'entity_type' => 'product',
                    'entity_id' => $product->id,
                    'before_data' => json_encode(['storage_location_id' => $before], JSON_THROW_ON_ERROR),
                    'after_data' => json_encode(['storage_location_id' => $locationId], JSON_THROW_ON_ERROR),
                    'reason' => null,
                    'created_at' => now(),
                ]);
            });
        }

        return response()->json(['product' => $product->fresh()->load(['category', 'fitments', 'storageLocation'])]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->file('image')) {
            return null;
        }
        if (config('partshelf.file_transport') === 'object') {
            abort_unless($request->attributes->get('verified_image_key'), 422, 'Use a verified direct upload.');

            return $request->file('image')->storeAs('products', basename($request->attributes->get('verified_image_key')), config('partshelf.image_disk'));
        }

        return $request->file('image')->store('products', config('partshelf.image_disk'));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $validator = Validator::make($request->all(), [
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'base_unit' => ['required', 'string', 'max:40'],
            'min_stock_quantity' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'fitments' => ['sometimes', 'array', 'max:50'],
            'fitments.*.make' => ['required', 'string', 'max:100'],
            'fitments.*.model' => ['required', 'string', 'max:120'],
            'fitments.*.year' => ['required', 'integer', 'min:1886', 'max:2200'],
            'fitments.*.engine' => ['nullable', 'string', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request, $product): void {
            $sku = trim((string) $request->input('sku'));
            $skuQuery = Product::query()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)]);

            if ($product) {
                $skuQuery->whereKeyNot($product->id);
            }

            if ($skuQuery->exists()) {
                $validator->errors()->add('sku', 'The SKU has already been taken.');
            }

            $categoryId = $request->input('category_id');
            if ($categoryId) {
                $category = Category::query()->find($categoryId);
                if ($category?->parent?->parent_id !== null) {
                    $validator->errors()->add('category_id', 'Categories may have a maximum of two levels.');
                }
            }
        });

        $validated = $validator->validate();
        foreach (['sku', 'name', 'part_number', 'base_unit'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        return $validated;
    }

    private function attributes(array $validated, ?string $imagePath, ?Product $product = null): array
    {
        return [
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'part_number' => $validated['part_number'] ?? $product?->part_number,
            'base_unit' => $validated['base_unit'],
            'min_stock_quantity' => $validated['min_stock_quantity'],
            'category_id' => $validated['category_id'] ?? $product?->category_id,
            'image_path' => $imagePath,
        ];
    }

    private function syncFitments(Product $product, array $fitments): void
    {
        $product->fitments()->delete();

        foreach ($fitments as $fitment) {
            $product->fitments()->create([
                'make' => trim($fitment['make']),
                'model' => trim($fitment['model']),
                'year' => (int) $fitment['year'],
                'engine' => trim((string) ($fitment['engine'] ?? '')),
            ]);
        }
    }

    private function snapshot(Product $product): array
    {
        return [
            'product' => $product->only(['sku', 'name', 'part_number', 'base_unit', 'category_id', 'storage_location_id', 'min_stock_quantity', 'image_path']),
            'fitments' => $product->fitments()->get(['make', 'model', 'year', 'engine'])->toArray(),
        ];
    }

    private function audit(Request $request, string $action, Product $product, ?array $before): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'product',
            'entity_id' => $product->id,
            'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_data' => json_encode($this->snapshot($product), JSON_THROW_ON_ERROR),
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
