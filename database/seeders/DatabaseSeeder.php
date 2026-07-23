<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            DB::table('product_add_ons')->truncate();
            DB::table('product_recipes')->truncate();
            AddOn::truncate();
            Product::truncate();
            Ingredient::truncate();
            Category::truncate();
            KitchenStation::truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        KitchenStation::create([
            'name' => 'Barra Caliente',
            'description' => 'Bebidas calientes y espresso',
            'target_prep_time_seconds' => 120,
        ]);
        KitchenStation::create([
            'name' => 'Barra Fría',
            'description' => 'Bebidas frías y jugos',
            'target_prep_time_seconds' => 180,
        ]);
        KitchenStation::create([
            'name' => 'Postres',
            'description' => 'Panadería y postres',
            'target_prep_time_seconds' => 60,
        ]);

        Category::create(['name' => 'Bebidas Calientes', 'slug' => 'bebidas-calientes']);
        Category::create(['name' => 'Bebidas Frías', 'slug' => 'bebidas-frias']);
        Category::create(['name' => 'Postres', 'slug' => 'postres']);

        $this->call(CatalogRecipeSeeder::class);

        $vanilla = Ingredient::where('sku', 'ING-VAINILLA')->firstOrFail();
        $almondMilk = Ingredient::where('sku', 'ING-ALMENDRA')->firstOrFail();

        $extraVanilla = AddOn::create([
            'name' => 'Shot de Vainilla',
            'price_adjustment' => 15,
            'ingredient_id' => $vanilla->id,
            'quantity_required' => 30,
        ]);
        $almondSubstitution = AddOn::create([
            'name' => 'Cambio a Leche de Almendra',
            'price_adjustment' => 12,
            'ingredient_id' => $almondMilk->id,
            'quantity_required' => 250,
        ]);

        Product::whereIn('sku', ['LAT-01', 'MOC-01'])->get()->each(
            fn (Product $product) => $product->addOns()->syncWithoutDetaching([
                $extraVanilla->id,
                $almondSubstitution->id,
            ])
        );

        $this->call(ReservationDemoSeeder::class);
    }
}
