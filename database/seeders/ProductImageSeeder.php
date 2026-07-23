<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProductImageSeeder extends Seeder
{
    private const IMAGES = [
        'LAT-01' => 'img/products/cafe-latte-reserva.jpg',
        'CAP-01' => 'img/products/cappuccino-clasico.jpg',
        'COL-01' => 'img/products/cold-brew-ahumado.jpg',
        'ESP-02' => 'img/products/espresso-doble.jpg',
        'AME-01' => 'img/products/americano-intenso.jpg',
        'FLA-01' => 'img/products/flat-white-mistico.jpg',
        'MOC-01' => 'img/products/mocha-dorado.jpg',
        'MAC-01' => 'img/products/macchiato.jpg',
        'AFF-01' => 'img/products/affogato.jpg',
        'CRO-01' => 'img/products/croissant-almendra.jpg',
        'CHO-01' => 'img/products/pan-au-chocolat.jpg',
        'CHE-01' => 'img/products/tarta-queso-vasco.jpg',
        'MUF-01' => 'img/products/muffin-arandanos.jpg',
        'GAL-01' => 'img/products/galleta-chispas-belga.jpg',
        'ECL-01' => 'img/products/eclair-vainilla.jpg',
        'MAT-01' => 'img/products/matcha-ceremonial.jpg',
        'CHA-01' => 'img/products/chai-latte.jpg',
        'INF-01' => 'img/products/infusion-frutos-rojos.jpg',
        'AGU-01' => 'img/products/agua-mineral-premium.jpg',
        'JUG-01' => 'img/products/jugo-verde-detox.jpg',
    ];

    public function run(): void
    {
        foreach (self::IMAGES as $sku => $imageUrl) {
            $updated = Product::query()->where('sku', $sku)->update(['image_url' => $imageUrl]);

            if ($updated !== 1) {
                throw new RuntimeException("No se pudo actualizar la imagen del producto {$sku}.");
            }
        }
    }
}
