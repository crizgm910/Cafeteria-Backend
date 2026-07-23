<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Services\CategoryAddOnService;
use App\Services\PublicMenuCache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Category::withCount('products')->orderBy('name')->get()
        );
    }

    public function store(Request $request, CategoryAddOnService $addOnService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
            'active' => ['sometimes', 'boolean'],
        ] + $this->addOnRules());

        $category = DB::transaction(function () use ($validated, $addOnService): Category {
            $category = Category::create([
                'name' => trim($validated['name']),
                'slug' => $this->uniqueSlug($validated['name']),
                'active' => $validated['active'] ?? true,
            ]);
            if (array_key_exists('add_ons', $validated)) {
                $addOnService->sync($category, $validated['add_ons']);
            }
            return $category;
        });

        app(PublicMenuCache::class)->forget();

        return response()->json($category->loadCount('products'), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->loadCount('products'));
    }

    public function update(Request $request, Category $category, CategoryAddOnService $addOnService): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'active' => ['sometimes', 'boolean'],
        ] + $this->addOnRules());

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
            $validated['slug'] = $this->uniqueSlug($validated['name'], $category->id);
        }

        $categoryData = collect($validated)->except('add_ons')->all();
        DB::transaction(function () use ($category, $categoryData, $validated, $addOnService): void {
            $category->update($categoryData);
            if (array_key_exists('add_ons', $validated)) {
                $addOnService->sync($category, $validated['add_ons']);
            }
        });

        app(PublicMenuCache::class)->forget();

        return response()->json($category->fresh()->loadCount('products'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $productsCount = $category->products()->count();

        if ($productsCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene productos asociados.',
                'products_count' => $productsCount,
            ], 409);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'categoria';
        $slug = $baseSlug;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function addOnRules(): array
    {
        return [
            'add_ons' => ['sometimes', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', 'exists:add_ons,id'],
            'add_ons.*.visible' => ['required', 'boolean'],
            'add_ons.*.selected_by_default' => ['required', 'boolean'],
            'add_ons.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'add_ons.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'add_ons.*.override_recipe' => ['sometimes', 'boolean'],
            'add_ons.*.recipe' => ['sometimes', 'array'],
            'add_ons.*.recipe.*.ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id'],
            'add_ons.*.recipe.*.quantity_required' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
