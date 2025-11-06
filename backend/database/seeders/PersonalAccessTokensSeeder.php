<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AppUser; // Asume tu modelo para app_users con HasApiTokens

class PersonalAccessTokensSeeder extends Seeder
{
    public function run(): void
    {
        $appUsers = AppUser::all(); // Usa los del seeder anterior

        foreach ($appUsers as $user) {
            $user->createToken(
                'API Token para ' . $user->name, // Name
                ['*'], // Abilities (full access para prueba; ajusta por roles)
                now()->addYear() // Expires at
            );
        }
    }
}
