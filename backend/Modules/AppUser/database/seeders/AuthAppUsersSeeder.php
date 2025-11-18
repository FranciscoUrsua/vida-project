<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AppUser\Models\AppUser; // Asume modelo Eloquent para app_users

class AuthAppUsersSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'name' => 'Admin Global',
                'email' => 'admin@vida.madrid.es',
                'email_verified_at' => now(),
                'password' => Hash::make('password'), // Cambia en prod
            ],
            [
                'name' => 'Trabajador Social Ejemplo',
                'email' => 'trabajador@vida.madrid.es',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Supervisor Distrital',
                'email' => 'supervisor@vida.madrid.es',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($usuarios as $usuario) {
            AppUser::firstOrCreate(
                ['email' => $usuario['email']],
                $usuario
            );
        }
    }
}
