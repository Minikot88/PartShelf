<?php

namespace App\Http\Controllers;

use App\Models\StorageLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StorageLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $locations = StorageLocation::query()
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($query) use ($term): void {
                    foreach (['zone', 'shelf', 'level', 'bin'] as $column) {
                        $query->orWhereRaw("LOWER({$column}) LIKE ?", [$term]);
                    }

                    $query->orWhereRaw("LOWER(CONCAT_WS('-', zone, shelf, level, bin)) LIKE ?", [$term]);
                });
            })
            ->orderBy('zone')->orderBy('shelf')->orderBy('level')->orderBy('bin')
            ->paginate(50)->withQueryString();

        return response()->json($locations);
    }

    public function products(StorageLocation $location): JsonResponse
    {
        return response()->json($location->products()
            ->with(['category', 'fitments'])
            ->orderBy('sku')
            ->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $location = DB::transaction(function () use ($validated, $request): StorageLocation {
            $location = StorageLocation::create($validated);
            $this->audit($request, 'storage_location.created', $location, null);

            return $location;
        });

        return response()->json(['location' => $location], 201);
    }

    public function update(Request $request, StorageLocation $location): JsonResponse
    {
        $validated = $this->validated($request, $location);
        $before = $location->only(['zone', 'shelf', 'level', 'bin']);

        DB::transaction(function () use ($location, $validated, $before, $request): void {
            $location->update($validated);
            $this->audit($request, 'storage_location.updated', $location, $before);
        });

        return response()->json(['location' => $location->fresh()], 200);
    }

    private function validated(Request $request, ?StorageLocation $location = null): array
    {
        $fields = ['zone' => 64, 'shelf' => 64, 'level' => 64, 'bin' => 64];
        $rules = [];
        foreach ($fields as $field => $max) {
            $rules[$field] = ['required', 'string', "max:{$max}"];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $location): void {
            $values = [];
            foreach (['zone', 'shelf', 'level', 'bin'] as $field) {
                $values[$field] = mb_strtolower(trim((string) $request->input($field)));
            }

            $query = StorageLocation::query();
            foreach ($values as $field => $value) {
                $query->whereRaw("LOWER({$field}) = ?", [$value]);
            }
            if ($location) {
                $query->whereKeyNot($location->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('bin', 'This storage location path already exists.');
            }
        });

        $validated = $validator->validate();
        foreach ($fields as $field => $max) {
            $validated[$field] = trim($validated[$field]);
        }

        return $validated;
    }

    private function audit(Request $request, string $action, StorageLocation $location, ?array $before): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'storage_location',
            'entity_id' => $location->id,
            'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_data' => json_encode($location->only(['zone', 'shelf', 'level', 'bin']), JSON_THROW_ON_ERROR),
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
