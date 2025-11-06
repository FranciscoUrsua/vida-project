<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Director;
use App\Models\Profesional;
use App\Models\Centro;

class DirectoresSeeder extends Seeder
{
    public function run(): void
    {
        $profIds = Profesional::pluck('id')->toArray();
        $centroIds = Centro::pluck('id')->toArray();

        $directores = [
            [
                'profesional_id' => $profIds[0] ?? 1, // Ana García
                'centro_id' => $centroIds[0] ?? 1, // Arganzuela
                'fecha_alta' => '2023-01-01',
                'fecha_baja' => null,
            ],
            [
                'profesional_id' => $profIds[1] ?? 2, // Carlos Martínez
                'centro_id' => $centroIds[2] ?? 3, // Fuencarral
                'fecha_alta' => '2024-06-15',
                'fecha_baja' => '2025-12-31', // Temporal
            ],
            [
                'profesional_id' => $profIds[3] ?? 4, // David López
                'centro_id' => $centroIds[3] ?? 4, // Latina
                'fecha_alta' => '2022-09-10',
                'fecha_baja' => null,
            ],
        ];

        foreach ($directores as $director) {
            Director::firstOrCreate(
                ['profesional_id' => $director['profesional_id'], 'centro_id' => $director['centro_id']],
                $director
            );
        }
    }
}
