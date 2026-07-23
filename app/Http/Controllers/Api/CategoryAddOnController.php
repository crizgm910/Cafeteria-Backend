<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PublicMenuCache;
use App\Services\CategoryAddOnService;

class CategoryAddOnController extends Controller
{
    public function index(Category $category)
    {
        $configured = DB::table('category_add_ons')->where('category_id', $category->id)->get()->keyBy('add_on_id');
        $recipes = DB::table('category_add_on_recipes')->where('category_id', $category->id)->get()->groupBy('add_on_id');

        return response()->json(AddOn::with('recipeItems.ingredient')->orderBy('sort_order')->orderBy('name')->get()
            ->map(function (AddOn $addOn) use ($configured, $recipes) {
                $row = $configured->get($addOn->id);
                return [
                    'id' => $addOn->id, 'name' => $addOn->name, 'active' => (bool) $addOn->active,
                    'price_adjustment' => (float) $addOn->price_adjustment,
                    'configured' => (bool) $row,
                    'visible' => $row ? (bool) $row->visible : false,
                    'selected_by_default' => $row ? (bool) $row->selected_by_default : false,
                    'price_override' => $row?->price_override === null ? null : (float) $row->price_override,
                    'sort_order' => $row ? (int) $row->sort_order : (int) $addOn->sort_order,
                    'override_recipe' => $row ? (bool) $row->override_recipe : false,
                    'recipe' => ($recipes->get($addOn->id) ?? collect())->values(),
                    'global_recipe' => $addOn->recipeItems,
                ];
            }));
    }

    public function update(Request $request, Category $category, CategoryAddOnService $service)
    {
        $validated = $request->validate([
            'add_ons' => 'present|array',
            'add_ons.*.add_on_id' => 'required|integer|distinct|exists:add_ons,id',
            'add_ons.*.visible' => 'required|boolean',
            'add_ons.*.selected_by_default' => 'required|boolean',
            'add_ons.*.price_override' => 'nullable|numeric|min:0',
            'add_ons.*.sort_order' => 'required|integer|min:0|max:9999',
            'add_ons.*.override_recipe' => 'sometimes|boolean',
            'add_ons.*.recipe' => 'sometimes|array',
            'add_ons.*.recipe.*.ingredient_id' => 'required|integer|distinct|exists:ingredients,id',
            'add_ons.*.recipe.*.quantity_required' => 'required|numeric|gt:0',
        ]);

        DB::transaction(fn () => $service->sync($category, $validated['add_ons']));

        app(PublicMenuCache::class)->forget();

        return $this->index($category);
    }
}
