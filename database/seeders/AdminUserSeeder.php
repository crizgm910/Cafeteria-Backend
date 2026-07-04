<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'cm7095921@gmail.com'],
            [
                'name' => 'Admin TGR',
                'password' => Hash::make('crizgm910'),
            ]
        );
    }
}
