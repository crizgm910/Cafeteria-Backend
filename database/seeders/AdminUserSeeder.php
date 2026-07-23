<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('TGR_OWNER_EMAIL'));
        $password = (string) env('TGR_OWNER_PASSWORD');
        if ($email === '' || $password === '') {
            $this->command?->warn('No se creó propietario: define TGR_OWNER_EMAIL y TGR_OWNER_PASSWORD.');
            return;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            throw new RuntimeException('El propietario requiere un correo válido y contraseña de al menos 12 caracteres.');
        }

        $user = User::updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => env('TGR_OWNER_NAME', 'Propietario TGR'),
                'password' => Hash::make($password),
                'active' => true,
            ]
        );
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'owner')->firstOrFail()->id]);
    }
}
