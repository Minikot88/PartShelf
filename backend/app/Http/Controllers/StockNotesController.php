<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ObjectFiles;
use App\Services\StockNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockNotesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->validate(['stock_status' => ['nullable', Rule::in(['IN_STOCK', 'LOW_STOCK', 'OUT_OF_STOCK', 'UNKNOWN', 'UNSET'])], 'tracking_mode' => ['nullable', Rule::in(['QUANTITY', 'AVAILABILITY_ONLY'])], 'archived' => ['nullable', Rule::in(['only', 'all'])], 'reorder' => ['nullable', 'boolean'], 'search' => ['nullable', 'string', 'max:255']]);
        $status = "CASE WHEN products.tracking_mode = 'AVAILABILITY_ONLY' THEN products.availability_status WHEN b.quantity IS NULL THEN 'UNSET' WHEN b.quantity = 0 THEN 'OUT_OF_STOCK' WHEN b.quantity <= products.min_stock_quantity THEN 'LOW_STOCK' ELSE 'IN_STOCK' END";
        $query = Product::query()->leftJoin('inventory_balances as b', 'b.product_id', '=', 'products.id')->select('products.*');
        if (($filter['archived'] ?? '') !== 'all') {
            ($filter['archived'] ?? '') === 'only' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        if (! empty($filter['stock_status'])) {
            $query->whereRaw("($status) = ?", [$filter['stock_status']]);
        }
        if (! empty($filter['tracking_mode'])) {
            $query->where('tracking_mode', $filter['tracking_mode']);
        }
        if (! empty($filter['reorder'])) {
            $query->where(fn ($q) => $q->where('reorder_needed', true)->orWhereRaw("($status) IN ('OUT_OF_STOCK','LOW_STOCK')"));
        }
        if (($search = trim($filter['search'] ?? '')) !== '') {
            $query->where(function ($q) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                foreach (['sku', 'name', 'part_number', 'notes'] as $field) {
                    $q->orWhereRaw("LOWER(products.$field) LIKE ?", [$term]);
                }
                $q->orWhereHas('storageLocation', fn ($l) => $l->whereRaw("LOWER(CONCAT_WS('-', zone,shelf,level,bin)) LIKE ?", [$term]));
                $q->orWhereHas('fitments', fn ($f) => $f->whereRaw("LOWER(CONCAT_WS(' ',make,model,year,engine)) LIKE ?", [$term]));
                if (preg_match('/^PS-(\d{8,})$/i', $search, $matches)) {
                    $q->orWhere('products.id', (int) $matches[1]);
                }
            });
        }

        return response()->json($query->with(['inventoryBalance', 'category', 'storageLocation', 'fitments'])->orderByDesc('products.id')->paginate(25)->through(fn ($p) => StockNotes::present($p)));
    }

    public function history(Product $product): JsonResponse
    {
        $events = DB::table('product_events as e')->join('users as u', 'u.id', '=', 'e.actor_id')->where('product_id', $product->id)->orderByDesc('e.id')
            ->select('e.*', 'u.name as actor_name')->paginate(25)->through(function ($e) {
                foreach (['before_data', 'after_data'] as $field) {
                    $e->$field = $e->$field === null ? null : json_decode($e->$field, true);
                }
                unset($e->response_data, $e->request_fingerprint, $e->idempotency_key);

                return $e;
            });

        return response()->json($events);
    }

    public function store(Request $request, StockNotes $notes): JsonResponse
    {
        abort_unless($request->user()->canManageCatalog(), 403);
        if ($request->filled('image_token')) {
            return $this->consumeImage($request, $notes, null, fn ($verified) => $this->store($verified, $notes));
        }
        $data = $this->metadata($request, true);
        abort_if($data['tracking_mode'] === 'AVAILABILITY_ONLY' && isset($data['initial_quantity']), 422, 'Availability-only products do not accept quantities.');
        [$body, $replay] = $notes->mutate($request, 'create', $data);

        return response()->json($body, $replay ? 200 : 201);
    }

    public function update(Request $request, Product $product, StockNotes $notes): JsonResponse
    {
        abort_unless($request->user()->canManageCatalog(), 403);
        if ($request->filled('image_token')) {
            return $this->consumeImage($request, $notes, $product, fn ($verified) => $this->update($verified, $product, $notes));
        }
        [$body] = $notes->mutate($request, 'edit', $this->metadata($request, false, $product), $product->id);

        return response()->json($body);
    }

    public function action(Request $request, Product $product, StockNotes $notes): JsonResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['sale', 'receipt', 'set_quantity', 'availability', 'reorder', 'archive', 'restore'])]])['action'];
        abort_unless(in_array($action, ['sale', 'availability', 'reorder'], true) || $request->user()->canManageStock(), 403);
        $rules = ['action' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:2000']];
        if (! in_array($action, ['sale', 'receipt'], true)) {
            $rules['expected_version'] = ['required', 'integer', 'min:0'];
        }
        if (in_array($action, ['sale', 'receipt', 'set_quantity'], true)) {
            $rules['quantity'] = $this->quantityRules($action === 'set_quantity' ? 0 : 0.001);
        }
        if ($action === 'set_quantity') {
            $rules['reason'] = ['required', 'string', 'max:2000'];
        }
        if ($action === 'availability') {
            $rules['availability_status'] = ['required', Rule::in(['IN_STOCK', 'OUT_OF_STOCK', 'UNKNOWN'])];
        }
        if ($action === 'reorder') {
            $rules += ['reorder_needed' => ['required', 'boolean'], 'reorder_note' => ['nullable', 'string', 'max:2000']];
        }
        [$body, $replay] = $notes->mutate($request, $action, $request->validate($rules), $product->id);

        return response()->json($body, $replay ? 200 : 201);
    }

    private function quantityRules(float $minimum = 0): array
    {
        return ['required', 'numeric', 'min:'.$minimum, 'max:99999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'];
    }

    private function metadata(Request $request, bool $create, ?Product $product = null): array
    {
        if (is_string($request->input('fitments'))) {
            $request->merge(['fitments' => json_decode($request->input('fitments'), true)]);
        }
        $rules = [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'tracking_mode' => [$create ? 'required' : 'sometimes', Rule::in(['QUANTITY', 'AVAILABILITY_ONLY'])],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'part_number' => ['sometimes', 'nullable', 'string', 'max:120'], 'base_unit' => ['sometimes', 'string', 'max:40'],
            'min_stock_quantity' => ['sometimes', 'numeric', 'min:0', 'max:99999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'storage_location_id' => ['sometimes', 'nullable', 'integer', 'exists:storage_locations,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'availability_status' => ['sometimes', Rule::in(['IN_STOCK', 'OUT_OF_STOCK', 'UNKNOWN'])],
            'initial_quantity' => ['sometimes', ...$this->quantityRules()],
            'confirm_mode_change' => ['sometimes', 'boolean'], 'reason' => ['sometimes', 'string', 'max:2000'],
            'fitments' => ['sometimes', 'array', 'max:50'], 'fitments.*.make' => ['required', 'string', 'max:100'],
            'fitments.*.model' => ['required', 'string', 'max:120'], 'fitments.*.year' => ['required', 'integer', 'min:1886', 'max:2200'], 'fitments.*.engine' => ['nullable', 'string', 'max:100'],
        ];
        if (! $create) {
            $rules['expected_version'] = ['required', 'integer', 'min:0'];
        }
        $data = $request->validate($rules);
        unset($data['image']);
        if ($request->hasFile('image')) {
            abort_if(config('partshelf.file_transport') === 'object' && ! $request->attributes->get('verified_image_key'), 422, 'Use a verified direct upload.');
            $data['_image_sha256'] = hash_file('sha256', $request->file('image')->getRealPath());
        }
        if (! $create && (! isset($data['sku']) || trim($data['sku']) === '')) {
            unset($data['sku']);
        }

        return $data;
    }

    private function consumeImage(Request $request, StockNotes $notes, ?Product $product, callable $callback): JsonResponse
    {
        $request->validate(['image_token' => ['required', 'uuid']]);

        return app(ObjectFiles::class)->consume($request, $request->input('image_token'), 'image', function ($file, $row) use ($request, $callback) {
            $request->files->set('image', $file);
            $verified = Request::createFrom($request);
            $verified->merge(['image_token' => null]);
            $verified->attributes->set('verified_image_key', $row->final_key);

            return $callback($verified);
        }, function ($row) use ($request, $notes, $product) {
            $data = $this->metadata($request, $product === null, $product);
            $data['_image_sha256'] = $row->sha256;

            return response()->json($notes->replayConsumedImage($request, $product === null ? 'create' : 'edit', $data, $product?->id));
        });
    }
}
