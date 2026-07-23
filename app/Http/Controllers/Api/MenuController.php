<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Services\AddOnConfigurationResolver;
use App\Services\PublicMenuCache;

class MenuController extends Controller
{
    public function index(AddOnConfigurationResolver $resolver, PublicMenuCache $cache)
    {
        $menu = $cache->remember(function () use ($resolver) {
            $menu = Category::where('active', true)->with(['products' => function($q) {
                $q->where('active', true)
                    ->whereHas('ingredients')
                    ->whereDoesntHave('ingredients', fn ($ingredients) => $ingredients
                        ->whereColumn('ingredients.current_stock', '<', 'product_recipes.quantity_required'))
                    ->with(['ingredients']);
            }])->orderBy('name')->get();

            $resolver->prime($menu->flatMap(fn (Category $category) => $category->products));
            $menu->each(function (Category $category) use ($resolver): void {
                $category->products->each(function ($product) use ($resolver): void {
                    $product->setAttribute('add_ons', $resolver->resolve($product)->map(fn ($item) => collect($item)->except('recipe')->all()));
                });
            });

            return $menu;
        });

        return response()->json($menu);
    }
}
