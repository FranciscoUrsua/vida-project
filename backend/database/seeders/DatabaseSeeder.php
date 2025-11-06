<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            // Autenticación, acceso y permisos
            AuthAppUsersSeeder::class,
            CacheTablesSeeder::class,
            QueueTablesSeeder::class,
            PersonalAccessTokensSeeder::class,
            AuditsSeeder::class,

            // Auxiliares
            CountriesSeeder::class,
            RegionsSeeder::class,
            TitulacionesSeeder::class,

            // Entidades principales
            ProfesionalesSeeder::class,
            CentrosSeeder::class,
            PrestacionesSeeder::class,
            SocialUsersSeeder::class,
            DirectoresSeeder::class,
            
            // Pivot
            CentroProfesionalSeeder::class,

        ]);
    }
}
