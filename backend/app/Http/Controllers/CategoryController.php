<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $category = DB::transaction(function () use ($validated, $request): Category {
            $category = Category::create($validated);
            $this->audit($request, 'category.created', $category, null);

            return $category;
        });

        return response()->json(['category' => $category->load('parent')], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $this->validated($request, $category);
        $before = $category->only(['name', 'parent_id']);

        DB::transaction(function () use ($category, $validated, $before, $request): void {
            $category->update($validated);
            $this->audit($request, 'category.updated', $category, $before);
        });

        return response()->json(['category' => $category->fresh()->load('parent')]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $validator->after(function ($validator) use ($request, $category): void {
            $name = trim((string) $request->input('name'));
            $parentId = $request->input('parent_id', $category?->parent_id);
            $query = Category::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

            if ($parentId === null || $parentId === '') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }

            if ($category) {
                $query->whereKeyNot($category->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('name', 'A category with this name already exists at this level.');
            }

            if ($parentId !== null && $parentId !== '') {
                $parent = Category::query()->find($parentId);

                if ($parent && ($parent->parent_id !== null || $parent->is($category))) {
                    $validator->errors()->add('parent_id', 'Categories may have a maximum of two levels.');
                }
            }
        });

        $validated = $validator->validate();
        $validated['name'] = trim($validated['name']);

        if (($validated['parent_id'] ?? null) === '') {
            $validated['parent_id'] = null;
        }

        return $validated;
    }

    private function audit(Request $request, string $action, Category $category, ?array $before): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'category',
            'entity_id' => $category->id,
            'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_data' => json_encode($category->only(['name', 'parent_id']), JSON_THROW_ON_ERROR),
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
