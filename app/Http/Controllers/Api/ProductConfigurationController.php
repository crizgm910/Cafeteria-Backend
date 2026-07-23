<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AddOnConfigurationResolver;
use App\Services\PublicMenuCache;
use App\Services\AdminCatalogCache;

class ProductConfigurationController extends Controller
{
    public function show(Product $product, AddOnConfigurationResolver $resolver)
    {
        return response()->json($this->configuration($product, $resolver));
    }

    public function updateRecipe(Request $request, Product $product)
    {
        $validated = $request->validate([
            'ingredients' => 'required|array|min:1',
            'ingredients.*.ingredient_id' => 'required|integer|distinct|exists:ingredients,id',
            'ingredients.*.quantity_required' => 'required|numeric|gt:0',
        ]);

        DB::transaction(function () use ($validated, $product): void {
            $recipe = collect($validated['ingredients'])->mapWithKeys(
                fn (array $item) => [$item['ingredient_id'] => [
                    'quantity_required' => $item['quantity_required'],
                ]]
            )->all();

            $product->ingredients()->sync($recipe);
        });

        app(PublicMenuCache::class)->forget();
        app(AdminCatalogCache::class)->forget();

        return response()->json($this->configuration($product->fresh(), app(AddOnConfigurationResolver::class)));
    }

    public function updateAddOns(Request $request, Product $product)
    {
        $validated = $request->validate([
            'add_on_ids' => 'sometimes|array',
            'add_on_ids.*' => 'integer|distinct|exists:add_ons,id',
            'add_ons' => 'sometimes|array',
            'add_ons.*.add_on_id' => 'required|integer|distinct|exists:add_ons,id',
            'add_ons.*.visible' => 'nullable|boolean',
            'add_ons.*.selected_by_default' => 'nullable|boolean',
            'add_ons.*.price_override' => 'nullable|numeric|min:0',
            'add_ons.*.sort_order' => 'nullable|integer|min:0|max:9999',
            'add_ons.*.override_recipe' => 'sometimes|boolean',
            'add_ons.*.recipe' => 'sometimes|array',
            'add_ons.*.recipe.*.ingredient_id' => 'required|integer|distinct|exists:ingredients,id',
            'add_ons.*.recipe.*.quantity_required' => 'required|numeric|gt:0',
        ]);

        $items = $validated['add_ons'] ?? collect($validated['add_on_ids'] ?? [])->map(fn ($id) => [
            'add_on_id' => $id, 'visible' => true, 'selected_by_default' => false,
        ])->all();
        DB::transaction(function () use ($product, $items): void {
            DB::table('product_add_on_recipes')->where('product_id', $product->id)->delete();
            $sync = [];
            foreach ($items as $item) {
                $overrideRecipe = (bool) ($item['override_recipe'] ?? false);
                if ($overrideRecipe && empty($item['recipe'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['add_ons' => 'Una receta personalizada no puede quedar vacía.']);
                }
                $sync[$item['add_on_id']] = [
                    'visible' => $item['visible'] ?? null,
                    'selected_by_default' => $item['selected_by_default'] ?? null,
                    'price_override' => $item['price_override'] ?? null,
                    'sort_order' => $item['sort_order'] ?? null,
                    'override_recipe' => $overrideRecipe,
                ];
                if ($overrideRecipe) foreach ($item['recipe'] as $recipe) {
                    DB::table('product_add_on_recipes')->insert($recipe + [
                        'product_id' => $product->id, 'add_on_id' => $item['add_on_id'],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $product->addOns()->sync($sync);
        });

        app(PublicMenuCache::class)->forget();

        return response()->json($this->configuration($product->fresh(), app(AddOnConfigurationResolver::class)));
    }

    private function configuration(Product $product, AddOnConfigurationResolver $resolver): array
    {
        $product->load(['ingredients:id,name,sku,unit_of_measure', 'addOns.ingredient']);

        return [
            'product_id' => $product->id,
            'is_sellable' => $product->active
                && $product->category_id !== null
                && $product->ingredients->isNotEmpty(),
            'ingredients' => $product->ingredients,
            'add_ons' => $resolver->resolve($product, true),
        ];
    }
}
