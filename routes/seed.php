<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/seed', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('products')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    
    $products = [
        ['category_id' => 1, 'name' => 'Café Latte Reserva', 'description' => 'Espresso de grano selecto con leche vaporizada.', 'price' => 65.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'LAT-01'],
        ['category_id' => 1, 'name' => 'Cappuccino Clásico', 'description' => 'Proporción perfecta de espresso, leche y espuma.', 'price' => 60.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'CAP-01'],
        ['category_id' => 1, 'name' => 'Cold Brew Ahumado', 'description' => 'Infusión en frío durante 24 horas con notas a madera.', 'price' => 85.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'COL-01'],
        ['category_id' => 1, 'name' => 'Espresso Doble', 'description' => 'Dosis doble de nuestro blend de la casa.', 'price' => 45.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'ESP-02'],
        ['category_id' => 1, 'name' => 'Americano Intenso', 'description' => 'Agua caliente sobre un espresso doble.', 'price' => 40.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'AME-01'],
        ['category_id' => 1, 'name' => 'Flat White Místico', 'description' => 'Espresso con leche microespumada suave.', 'price' => 70.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'FLA-01'],
        ['category_id' => 1, 'name' => 'Mocha Dorado', 'description' => 'Espresso con chocolate artesanal y leche.', 'price' => 75.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'MOC-01'],
        ['category_id' => 1, 'name' => 'Macchiato', 'description' => 'Espresso cortado con una mancha de espuma de leche.', 'price' => 50.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'MAC-01'],
        ['category_id' => 1, 'name' => 'Affogato', 'description' => 'Espresso vertido sobre helado de vainilla artesanal.', 'price' => 95.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'AFF-01'],
        
        ['category_id' => 3, 'name' => 'Croissant de Almendra', 'description' => 'Receta francesa auténtica horneada hoy.', 'price' => 55.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'CRO-01'],
        ['category_id' => 3, 'name' => 'Pan au Chocolat', 'description' => 'Masa hojaldrada con chocolate amargo.', 'price' => 60.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'CHO-01'],
        ['category_id' => 3, 'name' => 'Tarta de Queso Vasco', 'description' => 'Porción individual con textura cremosa.', 'price' => 90.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'CHE-01'],
        ['category_id' => 3, 'name' => 'Muffin de Arándanos', 'description' => 'Muffin esponjoso relleno de arándanos frescos.', 'price' => 45.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'MUF-01'],
        ['category_id' => 3, 'name' => 'Galleta de Chispas Belga', 'description' => 'Con trozos gigantes de chocolate belga.', 'price' => 35.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'GAL-01'],
        ['category_id' => 3, 'name' => 'Eclair de Vainilla', 'description' => 'Relleno de crema pastelera de vainilla de Madagascar.', 'price' => 70.00, 'active' => true, 'image_url' => 'img/premium_pastry.png', 'sku' => 'ECL-01'],
        
        ['category_id' => 2, 'name' => 'Té Matcha Ceremonial', 'description' => 'Preparado al estilo tradicional.', 'price' => 85.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'MAT-01'],
        ['category_id' => 2, 'name' => 'Chai Latte Especiado', 'description' => 'Mezcla de té negro y especias exóticas.', 'price' => 75.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'CHA-01'],
        ['category_id' => 2, 'name' => 'Infusión de Frutos Rojos', 'description' => 'Bebida sin cafeína, refrescante y frutal.', 'price' => 65.00, 'active' => true, 'image_url' => 'img/premium_latte.png', 'sku' => 'INF-01'],
        ['category_id' => 2, 'name' => 'Agua Mineral Premium', 'description' => 'Botella de cristal de 500ml.', 'price' => 40.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'AGU-01'],
        ['category_id' => 2, 'name' => 'Jugo Verde Detox', 'description' => 'Prensado en frío cada mañana.', 'price' => 75.00, 'active' => true, 'image_url' => 'img/premium_espresso.png', 'sku' => 'JUG-01'],
    ];
    
    foreach ($products as $p) {
        DB::table('products')->insert($p);
    }
    
    return response()->json(['message' => 'Seeded successfully!']);
});
