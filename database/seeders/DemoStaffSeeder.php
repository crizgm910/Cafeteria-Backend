<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('TGR_DEMO_STAFF_PASSWORD');

        if (mb_strlen($password) < 12) {
            throw new RuntimeException(
                'Define TGR_DEMO_STAFF_PASSWORD con al menos 12 caracteres antes de crear las cuentas de prueba.'
            );
        }

        $accounts = [
            'manager' => ['Gerencia de Pruebas', 'gerencia.pruebas@example.test'],
            'cashier' => ['Caja de Pruebas', 'caja.pruebas@example.test'],
            'preparation' => ['Preparación de Pruebas', 'preparacion.pruebas@example.test'],
            'inventory' => ['Inventario de Pruebas', 'inventario.pruebas@example.test'],
        ];

        foreach ($accounts as $roleSlug => [$name, $email]) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'active' => true,
                ]
            );

            $user->roles()->sync([$role->id]);
            $user->tokens()->delete();
        }
    }
}
