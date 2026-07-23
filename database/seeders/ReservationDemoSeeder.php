<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\ServiceArea;
use Illuminate\Database\Seeder;

class ReservationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            [
                'name' => 'Salón principal',
                'slug' => 'salon-principal',
                'description' => 'Área interior de demostración.',
                'sort_order' => 10,
                'tables' => [
                    ['code' => 'S01', 'name' => 'Mesa 1', 'max_capacity' => 2],
                    ['code' => 'S02', 'name' => 'Mesa 2', 'max_capacity' => 2],
                    ['code' => 'S03', 'name' => 'Mesa 3', 'max_capacity' => 4],
                    ['code' => 'S04', 'name' => 'Mesa 4', 'max_capacity' => 4],
                    ['code' => 'S05', 'name' => 'Mesa 5', 'max_capacity' => 6],
                ],
            ],
            [
                'name' => 'Terraza',
                'slug' => 'terraza',
                'description' => 'Área exterior de demostración.',
                'sort_order' => 20,
                'tables' => [
                    ['code' => 'T01', 'name' => 'Mesa 1', 'max_capacity' => 2],
                    ['code' => 'T02', 'name' => 'Mesa 2', 'max_capacity' => 4],
                    ['code' => 'T03', 'name' => 'Mesa 3', 'max_capacity' => 6],
                ],
            ],
            [
                'name' => 'Barra',
                'slug' => 'barra',
                'description' => 'Asientos individuales de demostración.',
                'sort_order' => 30,
                'tables' => [
                    ['code' => 'B01', 'name' => 'Lugar 1', 'max_capacity' => 1],
                    ['code' => 'B02', 'name' => 'Lugar 2', 'max_capacity' => 1],
                    ['code' => 'B03', 'name' => 'Lugar 3', 'max_capacity' => 1],
                    ['code' => 'B04', 'name' => 'Lugar 4', 'max_capacity' => 1],
                ],
            ],
        ];

        foreach ($areas as $areaData) {
            $tables = $areaData['tables'];
            unset($areaData['tables']);

            $area = ServiceArea::updateOrCreate(
                ['slug' => $areaData['slug']],
                $areaData + ['active' => true, 'public_visible' => true, 'reservable' => true],
            );

            foreach ($tables as $index => $tableData) {
                DiningTable::updateOrCreate(
                    ['service_area_id' => $area->id, 'code' => $tableData['code']],
                    $tableData + [
                        'min_capacity' => 1,
                        'status' => 'available',
                        'active' => true,
                        'reservable' => true,
                        'sort_order' => ($index + 1) * 10,
                    ],
                );
            }
        }
    }
}
