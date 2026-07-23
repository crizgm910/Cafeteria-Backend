<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddOnConfigurationResolver
{
    private ?Collection $primedProductIds = null;
    private ?Collection $primedCategoryRows = null;
    private ?Collection $primedProductRows = null;
    private ?Collection $primedAddOns = null;
    private ?Collection $primedCategoryRecipes = null;
    private ?Collection $primedProductRecipes = null;

    public function prime(Collection $products): void
    {
        $products = $products->filter()->values();
        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        $categoryIds = $products->pluck('category_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $this->primedProductIds = $productIds;
        $this->primedCategoryRows = $categoryIds->isEmpty()
            ? collect()
            : DB::table('category_add_ons')->whereIn('category_id', $categoryIds)->get()
                ->groupBy('category_id')->map(fn ($rows) => $rows->keyBy('add_on_id'));
        $this->primedProductRows = $productIds->isEmpty()
            ? collect()
            : DB::table('product_add_ons')->whereIn('product_id', $productIds)->get()
                ->groupBy('product_id')->map(fn ($rows) => $rows->keyBy('add_on_id'));

        $addOnIds = $this->primedCategoryRows
            ->flatMap(fn (Collection $rows) => $rows->keys())
            ->merge($this->primedProductRows->flatMap(fn (Collection $rows) => $rows->keys()))
            ->unique()
            ->values();
        $this->primedAddOns = $addOnIds->isEmpty()
            ? collect()
            : AddOn::with(['recipeItems.ingredient'])->whereIn('id', $addOnIds)->get()->keyBy('id');

        $this->primedCategoryRecipes = $this->preloadRecipeRows(
            'category_add_on_recipes', 'category_id', $categoryIds, $addOnIds
        );
        $this->primedProductRecipes = $this->preloadRecipeRows(
            'product_add_on_recipes', 'product_id', $productIds, $addOnIds
        );
    }

    public function resolve(Product $product, bool $includeHidden = false): Collection
    {
        $primed = $this->primedProductIds?->contains((int) $product->id) ?? false;
        $categoryRows = $primed
            ? ($this->primedCategoryRows->get($product->category_id) ?? collect())
            : ($product->category_id
                ? DB::table('category_add_ons')->where('category_id', $product->category_id)->get()->keyBy('add_on_id')
                : collect());
        $productRows = $primed
            ? ($this->primedProductRows->get($product->id) ?? collect())
            : DB::table('product_add_ons')->where('product_id', $product->id)->get()->keyBy('add_on_id');
        $ids = $categoryRows->keys()->merge($productRows->keys())->unique()->values();

        if ($ids->isEmpty()) return collect();

        $addOns = $primed
            ? $this->primedAddOns
            : AddOn::with(['recipeItems.ingredient'])->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(function ($id) use ($product, $categoryRows, $productRows, $addOns) {
            $addOn = $addOns->get($id);
            if (! $addOn) return null;
            $category = $categoryRows->get($id);
            $override = $productRows->get($id);

            $visible = $override && $override->visible !== null
                ? (bool) $override->visible
                : ($category ? (bool) $category->visible : (bool) $addOn->public_visible);
            $selected = $override && $override->selected_by_default !== null
                ? (bool) $override->selected_by_default
                : ($category ? (bool) $category->selected_by_default : false);
            $price = $override && $override->price_override !== null
                ? (float) $override->price_override
                : ($category && $category->price_override !== null
                    ? (float) $category->price_override
                    : (float) $addOn->price_adjustment);
            $sort = $override && $override->sort_order !== null
                ? (int) $override->sort_order
                : ($category ? (int) $category->sort_order : (int) $addOn->sort_order);

            $recipe = $this->recipe($product, $addOn, $category, $override);
            $available = $recipe->every(fn ($item) => (float) $item->current_stock >= (float) $item->quantity_required);

            return [
                'id' => $addOn->id,
                'name' => $addOn->name,
                'description' => $addOn->description,
                'price_adjustment' => $price,
                'effective_price' => $price,
                'selected_by_default' => $selected && $visible,
                'visible' => $visible,
                'available' => $available,
                'sort_order' => $sort,
                'source' => $override ? 'product' : 'category',
                'category_configured' => (bool) $category,
                'product_configured' => (bool) $override,
                'product_visible_override' => $override?->visible === null ? null : (bool) $override->visible,
                'product_selected_override' => $override?->selected_by_default === null ? null : (bool) $override->selected_by_default,
                'product_price_override' => $override?->price_override === null ? null : (float) $override->price_override,
                'product_sort_override' => $override?->sort_order === null ? null : (int) $override->sort_order,
                'override_recipe' => $override ? (bool) $override->override_recipe : false,
                'active' => (bool) $addOn->active,
                'public_visible' => (bool) $addOn->public_visible,
                'recipe' => $recipe->values()->all(),
            ];
        })->filter()->filter(fn ($item) => $includeHidden || (
            $item['active'] && $item['public_visible'] && $item['visible'] && $item['available']
        ))->sortBy(fn ($item) => sprintf('%010d-%s', $item['sort_order'], mb_strtolower($item['name'])))->values();
    }

    public function allowed(Product $product): Collection
    {
        return $this->resolve($product)->keyBy('id');
    }

    private function recipe(Product $product, AddOn $addOn, ?object $category, ?object $override): Collection
    {
        if ($override && (bool) $override->override_recipe) {
            if ($this->primedProductIds?->contains((int) $product->id)) {
                return $this->primedProductRecipes->get("{$product->id}:{$addOn->id}", collect());
            }
            return $this->recipeRows('product_add_on_recipes', [
                'product_id' => $product->id, 'add_on_id' => $addOn->id,
            ]);
        }
        if ($category && (bool) $category->override_recipe) {
            if ($this->primedProductIds?->contains((int) $product->id)) {
                return $this->primedCategoryRecipes->get("{$product->category_id}:{$addOn->id}", collect());
            }
            return $this->recipeRows('category_add_on_recipes', [
                'category_id' => $product->category_id, 'add_on_id' => $addOn->id,
            ]);
        }

        $recipe = $addOn->recipeItems->map(fn ($item) => (object) [
            'ingredient_id' => $item->ingredient_id,
            'name' => $item->ingredient?->name,
            'unit_of_measure' => $item->ingredient?->unit_of_measure,
            'current_stock' => $item->ingredient?->current_stock ?? 0,
            'quantity_required' => $item->quantity_required,
        ]);

        if ($recipe->isEmpty() && $addOn->ingredient_id && (float) $addOn->quantity_required > 0) {
            $recipe = $this->recipeRows('add_ons', ['id' => $addOn->id], true);
        }
        return $recipe;
    }

    private function recipeRows(string $table, array $where, bool $legacy = false): Collection
    {
        $query = DB::table($table);
        if ($legacy) {
            $query->join('ingredients', 'ingredients.id', '=', 'add_ons.ingredient_id')
                ->where('add_ons.id', $where['id'])
                ->selectRaw('ingredients.id as ingredient_id, ingredients.name, ingredients.unit_of_measure, ingredients.current_stock, add_ons.quantity_required');
        } else {
            $query->where($where);
            $query->join('ingredients', 'ingredients.id', '=', "{$table}.ingredient_id")
                ->selectRaw("ingredients.id as ingredient_id, ingredients.name, ingredients.unit_of_measure, ingredients.current_stock, {$table}.quantity_required");
        }
        return $query->get();
    }

    private function preloadRecipeRows(string $table, string $ownerColumn, Collection $ownerIds, Collection $addOnIds): Collection
    {
        if ($ownerIds->isEmpty() || $addOnIds->isEmpty()) return collect();

        return DB::table($table)
            ->join('ingredients', 'ingredients.id', '=', "{$table}.ingredient_id")
            ->whereIn("{$table}.{$ownerColumn}", $ownerIds)
            ->whereIn("{$table}.add_on_id", $addOnIds)
            ->selectRaw("{$table}.{$ownerColumn} as owner_id, {$table}.add_on_id, ingredients.id as ingredient_id, ingredients.name, ingredients.unit_of_measure, ingredients.current_stock, {$table}.quantity_required")
            ->get()
            ->groupBy(fn ($row) => "{$row->owner_id}:{$row->add_on_id}");
    }
}
