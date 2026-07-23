<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\KitchenStation;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogRecipeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = Category::whereIn('slug', [
                'bebidas-calientes',
                'bebidas-frias',
                'postres',
            ])->get()->keyBy('slug');

            $stations = KitchenStation::whereIn('name', [
                'Barra Caliente',
                'Barra Fría',
                'Postres',
            ])->get()->keyBy('name');

            if ($categories->count() !== 3 || $stations->count() !== 3) {
                throw new RuntimeException('Faltan categorías o estaciones requeridas para instalar el recetario.');
            }

            $ingredients = collect($this->ingredients())->mapWithKeys(function (array $definition, string $sku): array {
                $ingredient = Ingredient::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $definition['name'],
                        'unit_of_measure' => $definition['unit'],
                        'current_stock' => 0,
                        'minimum_stock' => 0,
                        'cost_per_unit' => 0,
                    ]
                );

                if ($ingredient->unit_of_measure !== $definition['unit']) {
                    throw new RuntimeException("El insumo {$sku} usa una unidad incompatible.");
                }

                return [$sku => $ingredient];
            });

            foreach ($this->products() as $sku => $definition) {
                $product = Product::firstOrNew(['sku' => $sku]);

                if (! $product->exists) {
                    $product->fill([
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'price' => $definition['price'],
                        'active' => true,
                    ]);
                }

                $product->category_id = $categories[$definition['category']]->id;
                $product->kitchen_station_id = $stations[$definition['station']]->id;
                $product->save();

                $recipe = collect($definition['recipe'])->mapWithKeys(
                    fn (float|int $quantity, string $ingredientSku): array => [
                        $ingredients[$ingredientSku]->id => ['quantity_required' => $quantity],
                    ]
                )->all();

                $product->ingredients()->sync($recipe);
            }
        });
    }

    private function ingredients(): array
    {
        return [
            'ING-CAFE' => ['name' => 'Grano de Café Espresso', 'unit' => 'gramos'],
            'ING-LECHE' => ['name' => 'Leche Entera', 'unit' => 'mililitros'],
            'ING-ALMENDRA' => ['name' => 'Leche de Almendra', 'unit' => 'mililitros'],
            'ING-VAINILLA' => ['name' => 'Jarabe de Vainilla', 'unit' => 'mililitros'],
            'ING-VASO8' => ['name' => 'Vaso 8oz', 'unit' => 'piezas'],
            'ING-VASO12' => ['name' => 'Vaso 12oz', 'unit' => 'piezas'],
            'ING-VASO16' => ['name' => 'Vaso 16oz', 'unit' => 'piezas'],
            'ING-CHOCOLATE' => ['name' => 'Salsa de Chocolate', 'unit' => 'mililitros'],
            'ING-HELADO-VAINILLA' => ['name' => 'Helado de Vainilla', 'unit' => 'gramos'],
            'ING-CROISSANT-ALM' => ['name' => 'Croissant de Almendra', 'unit' => 'piezas'],
            'ING-PAIN-CHOC' => ['name' => 'Pan au Chocolat', 'unit' => 'piezas'],
            'ING-TARTA-VASCA' => ['name' => 'Porción de Tarta de Queso Vasco', 'unit' => 'piezas'],
            'ING-MUFFIN-ARAND' => ['name' => 'Muffin de Arándanos', 'unit' => 'piezas'],
            'ING-GALLETA-BELGA' => ['name' => 'Galleta de Chispas Belga', 'unit' => 'piezas'],
            'ING-ECLAIR-VAINILLA' => ['name' => 'Eclair de Vainilla', 'unit' => 'piezas'],
            'ING-MATCHA' => ['name' => 'Matcha Ceremonial', 'unit' => 'gramos'],
            'ING-CHAI' => ['name' => 'Concentrado de Té Chai', 'unit' => 'mililitros'],
            'ING-INF-FRUTOS' => ['name' => 'Sachet de Infusión de Frutos Rojos', 'unit' => 'piezas'],
            'ING-AGUA-MIN500' => ['name' => 'Agua Mineral Premium 500ml', 'unit' => 'piezas'],
            'ING-PEPINO' => ['name' => 'Pepino', 'unit' => 'gramos'],
            'ING-MANZANA-VERDE' => ['name' => 'Manzana Verde', 'unit' => 'gramos'],
            'ING-APIO' => ['name' => 'Apio', 'unit' => 'gramos'],
            'ING-ESPINACA' => ['name' => 'Espinaca', 'unit' => 'gramos'],
            'ING-LIMON' => ['name' => 'Limón Verde', 'unit' => 'gramos'],
            'ING-JENGIBRE' => ['name' => 'Jengibre', 'unit' => 'gramos'],
        ];
    }

    private function products(): array
    {
        return [
            'LAT-01' => $this->product('Café Latte Reserva', 'Espresso de grano selecto con leche vaporizada.', 65, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-LECHE' => 250, 'ING-VASO16' => 1]),
            'CAP-01' => $this->product('Cappuccino Clásico', 'Proporción equilibrada de espresso, leche y espuma.', 60, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-LECHE' => 120, 'ING-VASO8' => 1]),
            'COL-01' => $this->product('Cold Brew Ahumado', 'Infusión en frío de extracción prolongada con perfil tostado.', 85, 'bebidas-frias', 'Barra Fría', ['ING-CAFE' => 29, 'ING-VASO16' => 1]),
            'ESP-02' => $this->product('Espresso Doble', 'Dosis doble del blend de la casa.', 45, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-VASO8' => 1]),
            'AME-01' => $this->product('Americano Intenso', 'Agua caliente sobre un espresso doble.', 40, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-VASO12' => 1]),
            'FLA-01' => $this->product('Flat White Místico', 'Espresso doble con leche microespumada suave.', 70, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-LECHE' => 100, 'ING-VASO8' => 1]),
            'MOC-01' => $this->product('Mocha Dorado', 'Espresso con chocolate y leche vaporizada.', 75, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-LECHE' => 200, 'ING-CHOCOLATE' => 30, 'ING-VASO12' => 1]),
            'MAC-01' => $this->product('Macchiato', 'Espresso doble marcado con espuma de leche.', 50, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-LECHE' => 20, 'ING-VASO8' => 1]),
            'AFF-01' => $this->product('Affogato', 'Espresso doble servido sobre helado de vainilla.', 95, 'bebidas-calientes', 'Barra Caliente', ['ING-CAFE' => 18, 'ING-HELADO-VAINILLA' => 80]),
            'CRO-01' => $this->product('Croissant de Almendra', 'Pieza de croissant de almendra horneada.', 55, 'postres', 'Postres', ['ING-CROISSANT-ALM' => 1]),
            'CHO-01' => $this->product('Pan au Chocolat', 'Pieza de masa hojaldrada con chocolate amargo.', 60, 'postres', 'Postres', ['ING-PAIN-CHOC' => 1]),
            'CHE-01' => $this->product('Tarta de Queso Vasco', 'Porción individual de tarta de queso de textura cremosa.', 90, 'postres', 'Postres', ['ING-TARTA-VASCA' => 1]),
            'MUF-01' => $this->product('Muffin de Arándanos', 'Muffin individual con arándanos.', 45, 'postres', 'Postres', ['ING-MUFFIN-ARAND' => 1]),
            'GAL-01' => $this->product('Galleta de Chispas Belga', 'Galleta individual con trozos de chocolate belga.', 35, 'postres', 'Postres', ['ING-GALLETA-BELGA' => 1]),
            'ECL-01' => $this->product('Eclair de Vainilla', 'Eclair individual relleno de crema de vainilla.', 70, 'postres', 'Postres', ['ING-ECLAIR-VAINILLA' => 1]),
            'MAT-01' => $this->product('Té Matcha Ceremonial', 'Matcha batido al estilo tradicional.', 85, 'bebidas-calientes', 'Barra Caliente', ['ING-MATCHA' => 2.5, 'ING-VASO8' => 1]),
            'CHA-01' => $this->product('Chai Latte Especiado', 'Concentrado de té chai con leche vaporizada.', 75, 'bebidas-calientes', 'Barra Caliente', ['ING-CHAI' => 30, 'ING-LECHE' => 300, 'ING-VASO16' => 1]),
            'INF-01' => $this->product('Infusión de Frutos Rojos', 'Infusión frutal sin cafeína preparada con agua recién hervida.', 65, 'bebidas-calientes', 'Barra Caliente', ['ING-INF-FRUTOS' => 1, 'ING-VASO16' => 1]),
            'AGU-01' => $this->product('Agua Mineral Premium', 'Botella de cristal de 500ml.', 40, 'bebidas-frias', 'Barra Fría', ['ING-AGUA-MIN500' => 1]),
            'JUG-01' => $this->product('Jugo Verde', 'Jugo verde preparado con vegetales, manzana, cítricos y jengibre.', 75, 'bebidas-frias', 'Barra Fría', ['ING-PEPINO' => 100, 'ING-MANZANA-VERDE' => 100, 'ING-APIO' => 70, 'ING-ESPINACA' => 30, 'ING-LIMON' => 15, 'ING-JENGIBRE' => 5, 'ING-VASO16' => 1]),
        ];
    }

    private function product(string $name, string $description, float $price, string $category, string $station, array $recipe): array
    {
        return compact('name', 'description', 'price', 'category', 'station', 'recipe');
    }
}
