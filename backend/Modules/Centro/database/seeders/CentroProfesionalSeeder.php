<?php

namespace Modules\Centro\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centro\Models\CentroProfesional; // Asume modelo para pivot
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Profesional;

class CentroProfesionalSeeder extends Seeder
{
    public function run(): void
    {
        $centroIds = Centro::pluck('id')->toArray();
        $profIds = Profesional::pluck('id')->toArray();

        $asignaciones = [
            ['centro_id' => $centroIds[0] ?? 1, 'profesional_id' => $profIds[0] ?? 1], // Ana en Arganzuela
            ['centro_id' => $centroIds[0] ?? 1, 'profesional_id' => $profIds[2] ?? 3], // María también en Arganzuela
            ['centro_id' => $centroIds[1] ?? 2, 'profesional_id' => $profIds[1] ?? 2], // Carlos en Chamberí
            ['centro_id' => $centroIds[4] ?? 5, 'profesional_id' => $profIds[4] ?? 5], // Laura en Usera
            ['centro_id' => $centroIds[2] ?? 3, 'profesional_id' => $profIds[3] ?? 4], // David en Fuencarral
        ];

        foreach ($asignaciones as $asign) {
            CentroProfesional::firstOrCreate(
                ['centro_id' => $asign['centro_id'], 'profesional_id' => $asign['profesional_id']]
            );
        }
    }
}
