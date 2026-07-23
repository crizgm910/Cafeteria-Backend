<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class DemoInventorySeeder extends Seeder
{
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Valores sintéticos para demostración. No representan un conteo físico ni
     * costos comprobados por factura.
     */
    private const INVENTORY = [
        'ING-VASO8' => ['stock' => 300, 'minimum' => 75, 'cost' => 1.80],
        'ING-VASO12' => ['stock' => 400, 'minimum' => 100, 'cost' => 2.10],
        'ING-CHOCOLATE' => ['stock' => 3000, 'minimum' => 600, 'cost' => 0.18],
        'ING-HELADO-VAINILLA' => ['stock' => 5000, 'minimum' => 1000, 'cost' => 0.16],
        'ING-CROISSANT-ALM' => ['stock' => 40, 'minimum' => 10, 'cost' => 24.00],
        'ING-PAIN-CHOC' => ['stock' => 40, 'minimum' => 10, 'cost' => 25.00],
        'ING-TARTA-VASCA' => ['stock' => 24, 'minimum' => 6, 'cost' => 32.00],
        'ING-MUFFIN-ARAND' => ['stock' => 36, 'minimum' => 8, 'cost' => 18.00],
        'ING-GALLETA-BELGA' => ['stock' => 60, 'minimum' => 15, 'cost' => 10.00],
        'ING-ECLAIR-VAINILLA' => ['stock' => 30, 'minimum' => 8, 'cost' => 26.00],
        'ING-MATCHA' => ['stock' => 500, 'minimum' => 100, 'cost' => 1.20],
        'ING-CHAI' => ['stock' => 3000, 'minimum' => 600, 'cost' => 0.12],
        'ING-INF-FRUTOS' => ['stock' => 80, 'minimum' => 20, 'cost' => 8.00],
        'ING-AGUA-MIN500' => ['stock' => 96, 'minimum' => 24, 'cost' => 12.00],
        'ING-PEPINO' => ['stock' => 6000, 'minimum' => 1500, 'cost' => 0.04],
        'ING-MANZANA-VERDE' => ['stock' => 8000, 'minimum' => 2000, 'cost' => 0.05],
        'ING-APIO' => ['stock' => 5000, 'minimum' => 1200, 'cost' => 0.04],
        'ING-ESPINACA' => ['stock' => 3000, 'minimum' => 700, 'cost' => 0.08],
        'ING-LIMON' => ['stock' => 5000, 'minimum' => 1000, 'cost' => 0.05],
        'ING-JENGIBRE' => ['stock' => 1500, 'minimum' => 300, 'cost' => 0.12],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::INVENTORY as $sku => $values) {
                $ingredient = Ingredient::query()->where('sku', $sku)->lockForUpdate()->first();

                if (! $ingredient) {
                    throw new RuntimeException("No existe el insumo {$sku}.");
                }

                $ingredient->minimum_stock = $values['minimum'];
                $ingredient->cost_per_unit = $values['cost'];

                $referenceId = (string) Uuid::uuid5(
                    self::UUID_NAMESPACE,
                    "tgr-demo-inventory:{$sku}"
                );

                $alreadyLoaded = InventoryTransaction::query()
                    ->where('reference_id', $referenceId)
                    ->exists();

                if (! $alreadyLoaded) {
                    $ingredient->current_stock += $values['stock'];

                    InventoryTransaction::query()->create([
                        'ingredient_id' => $ingredient->id,
                        'transaction_type' => 'restock',
                        'reference_id' => $referenceId,
                        'quantity' => $values['stock'],
                        'stock_after_transaction' => $ingredient->current_stock,
                    ]);
                }

                $ingredient->save();
            }
        });
    }
}
