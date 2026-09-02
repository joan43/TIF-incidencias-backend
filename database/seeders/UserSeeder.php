<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario común de prueba
        User::firstOrCreate(
            ['email' => 'usuario@test.com'],
            [
                'name' => 'Usuario Alumno',
                'password' => Hash::make('password123'),
                'role' => 'usuario'
            ]
        );

        // Técnico de prueba
        User::firstOrCreate(
            ['email' => 'tecnico@test.com'],
            [
                'name' => 'Tecnico Soporte',
                'password' => Hash::make('password123'),
                'role' => 'tecnico'
            ]
        );
    }
}