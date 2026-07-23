<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\AddOn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AdminCatalogCache;

class CatalogBootstrapController extends Controller
{
    public function __invoke(Request $request, AdminCatalogCache $cache): JsonResponse
    {
        $user = $request->user();
        $canManageCatalog = $user->hasPermission('catalog.manage');
        $canViewInventory = $user->hasPermission('inventory.view');

        [$categories, $products, $addOns] = $canManageCatalog
            ? $cache->catalog(fn () => [
                Category::withCount('products')->orderBy('name')->get(),
                Product::with(['category', 'ingredients:id,name,unit_of_measure'])
                    ->orderBy('name')->orderBy('id')->get()->each->append('is_sellable'),
                AddOn::with(['recipeItems.ingredient'])
                    ->withCount(['products', 'categories', 'ticketItems'])
                    ->orderBy('sort_order')->orderBy('name')->get(),
            ])
            : [collect(), collect(), collect()];
        $ingredients = $canViewInventory
            ? $cache->inventory(fn () => Ingredient::orderBy('name')->orderBy('id')->get())
            : collect();

        return response()->json([
            'categories' => $categories,
            'products' => $products,
            'add_ons' => $addOns,
            'ingredients' => $ingredients,
        ]);
    }
}
