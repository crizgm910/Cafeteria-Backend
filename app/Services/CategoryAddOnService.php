<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryAddOnService
{
    public function sync(Category $category, array $items): void
    {
        DB::table('category_add_on_recipes')->where('category_id', $category->id)->delete();
        DB::table('category_add_ons')->where('category_id', $category->id)->delete();

        foreach ($items as $item) {
            $overrideRecipe = (bool) ($item['override_recipe'] ?? false);
            if ($overrideRecipe && empty($item['recipe'])) {
                throw ValidationException::withMessages([
                    'add_ons' => 'Una receta personalizada no puede quedar vacía.',
                ]);
            }

            DB::table('category_add_ons')->insert([
                'category_id' => $category->id,
                'add_on_id' => $item['add_on_id'],
                'visible' => $item['visible'],
                'selected_by_default' => $item['visible'] ? $item['selected_by_default'] : false,
                'price_override' => $item['price_override'] ?? null,
                'sort_order' => $item['sort_order'],
                'override_recipe' => $overrideRecipe,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($overrideRecipe) {
                foreach ($item['recipe'] as $recipe) {
                    DB::table('category_add_on_recipes')->insert($recipe + [
                        'category_id' => $category->id,
                        'add_on_id' => $item['add_on_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
