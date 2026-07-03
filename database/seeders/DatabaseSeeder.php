<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\KitchenStation;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\AddOn;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Limpiar para evitar duplicados si se corre varias veces
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('product_add_ons')->truncate();
        DB::table('product_recipes')->truncate();
        AddOn::truncate();
        Product::truncate();
        Ingredient::truncate();
        Category::truncate();
        KitchenStation::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Estaciones de Cocina
        $barraCaliente = KitchenStation::create(['name' => 'Barra Caliente', 'description' => 'Bebidas calientes y espresso', 'target_prep_time_seconds' => 120]);
        $barraFria = KitchenStation::create(['name' => 'Barra Fría', 'description' => 'Frappés y bebidas frías', 'target_prep_time_seconds' => 180]);
        $postres = KitchenStation::create(['name' => 'Postres', 'description' => 'Pasteles y galletas', 'target_prep_time_seconds' => 60]);

        // 2. Categorías
        $catBebidas = Category::create(['name' => 'Bebidas Calientes', 'slug' => 'bebidas-calientes']);
        $catFrias = Category::create(['name' => 'Bebidas Frías', 'slug' => 'bebidas-frias']);
        $catPostres = Category::create(['name' => 'Postres', 'slug' => 'postres']);

        // 3. Ingredientes (Materia Prima)
        $cafe = Ingredient::create(['sku' => 'ING-CAFE', 'name' => 'Grano de Café Espresso', 'unit_of_measure' => 'gramos', 'current_stock' => 5000, 'minimum_stock' => 1000, 'cost_per_unit' => 0.20]);
        $leche = Ingredient::create(['sku' => 'ING-LECHE', 'name' => 'Leche Entera', 'unit_of_measure' => 'mililitros', 'current_stock' => 10000, 'minimum_stock' => 2000, 'cost_per_unit' => 0.02]);
        $lecheAlmendra = Ingredient::create(['sku' => 'ING-ALMENDRA', 'name' => 'Leche de Almendra', 'unit_of_measure' => 'mililitros', 'current_stock' => 5000, 'minimum_stock' => 1000, 'cost_per_unit' => 0.04]);
        $vaso = Ingredient::create(['sku' => 'ING-VASO16', 'name' => 'Vaso 16oz', 'unit_of_measure' => 'piezas', 'current_stock' => 500, 'minimum_stock' => 50, 'cost_per_unit' => 2.50]);
        $jarabeVainilla = Ingredient::create(['sku' => 'ING-VAINILLA', 'name' => 'Jarabe de Vainilla', 'unit_of_measure' => 'mililitros', 'current_stock' => 2000, 'minimum_stock' => 500, 'cost_per_unit' => 0.10]);
        $pastel = Ingredient::create(['sku' => 'ING-PASTELCHOCO', 'name' => 'Rebanada Pastel Choco', 'unit_of_measure' => 'piezas', 'current_stock' => 20, 'minimum_stock' => 5, 'cost_per_unit' => 15.00]);

        // 4. Productos (El Menú)
        $latte = Product::create([
            'category_id' => $catBebidas->id,
            'kitchen_station_id' => $barraCaliente->id,
            'sku' => 'PROD-LATTE-16',
            'name' => 'Café Latte 16oz',
            'description' => 'Clásico café latte con un shot de espresso',
            'price' => 55.00,
            'active' => true
        ]);
        
        $frappe = Product::create([
            'category_id' => $catFrias->id,
            'kitchen_station_id' => $barraFria->id,
            'sku' => 'PROD-FRAPPE-16',
            'name' => 'Frappé Moka 16oz',
            'description' => 'Frappé helado sabor moka',
            'price' => 70.00,
            'active' => true
        ]);

        $pastelChoco = Product::create([
            'category_id' => $catPostres->id,
            'kitchen_station_id' => $postres->id,
            'sku' => 'PROD-PASTEL-CHOCO',
            'name' => 'Pastel de Chocolate',
            'description' => 'Deliciosa rebanada de chocolate oscuro',
            'price' => 45.00,
            'active' => true
        ]);

        // 5. Recetas (Depletion)
        $latte->ingredients()->attach([
            $cafe->id => ['quantity_required' => 15],
            $leche->id => ['quantity_required' => 250],
            $vaso->id => ['quantity_required' => 1]
        ]);

        $frappe->ingredients()->attach([
            $cafe->id => ['quantity_required' => 15],
            $leche->id => ['quantity_required' => 200],
            $vaso->id => ['quantity_required' => 1]
        ]);

        $pastelChoco->ingredients()->attach([
            $pastel->id => ['quantity_required' => 1]
        ]);

        // 6. Complementos (Add-ons)
        $extraVainilla = AddOn::create([
            'name' => 'Shot de Vainilla',
            'price_adjustment' => 15.00,
            'ingredient_id' => $jarabeVainilla->id,
            'quantity_required' => 30
        ]);

        $cambioAlmendra = AddOn::create([
            'name' => 'Cambio a Leche de Almendra',
            'price_adjustment' => 12.00,
            'ingredient_id' => $lecheAlmendra->id,
            'quantity_required' => 250
        ]);

        // 7. Relación Producto <-> Complemento
        $latte->addOns()->attach([$extraVainilla->id, $cambioAlmendra->id]);
        $frappe->addOns()->attach([$extraVainilla->id, $cambioAlmendra->id]);
    }
}
