<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            // Autenticación, acceso y permisos
            AuditsSeeder::class,
            AuthAppUsersSeeder::class,
            CacheTablesSeeder::class,
            QueueTablesSeeder::class,
            PersonalAccessTokensSeeder::class,


            // Auxiliares
            CountriesSeeder::class,
            RegionsSeeder::class,
            TitulacionesSeeder::class,
            DistritosSeeder::class,

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
