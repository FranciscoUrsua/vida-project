<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distrito; // Asume modelo Eloquent para distritos

class DistritosSeeder extends Seeder
{
    public function run(): void
    {
        $distritos = [
            ['codigo' => '01', 'nombre' => 'Centro'],
            ['codigo' => '02', 'nombre' => 'Arganzuela'],
            ['codigo' => '03', 'nombre' => 'Retiro'],
            ['codigo' => '04', 'nombre' => 'Salamanca'],
            ['codigo' => '05', 'nombre' => 'Chamartín'],
            ['codigo' => '06', 'nombre' => 'Tetuán'],
            ['codigo' => '07', 'nombre' => 'Chamberí'],
            ['codigo' => '08', 'nombre' => 'Fuencarral - El Pardo'],
            ['codigo' => '09', 'nombre' => 'Moncloa - Aravaca'],
            ['codigo' => '10', 'nombre' => 'Latina'],
            ['codigo' => '11', 'nombre' => 'Carabanchel'],
            ['codigo' => '12', 'nombre' => 'Usera'],
            ['codigo' => '13', 'nombre' => 'Puente de Vallecas'],
            ['codigo' => '14', 'nombre' => 'Moratalaz'],
            ['codigo' => '15', 'nombre' => 'Ciudad Lineal'],
            ['codigo' => '16', 'nombre' => 'Hortaleza'],
            ['codigo' => '17', 'nombre' => 'Villaverde'],
            ['codigo' => '18', 'nombre' => 'Villa de Vallecas'],
            ['codigo' => '19', 'nombre' => 'Vicálvaro'],
            ['codigo' => '20', 'nombre' => 'San Blas - Canillejas'],
            ['codigo' => '21', 'nombre' => 'Barajas'],
        ];

        foreach ($distritos as $distrito) {
            Distrito::firstOrCreate(
                ['codigo' => $distrito['codigo']],
                $distrito
            );
        }
    }
}
