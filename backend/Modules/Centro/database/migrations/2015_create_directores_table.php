<?php

namespace Modules\Centro\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centro\Models\Director;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Profesional; // Asumiendo modelo Profesional existe y está seeded

class DirectoresSeeder extends Seeder
{
    public function run(): void
    {
        // Asumimos que centros y profesionales ya están seeded.
        // IDs de centros del seeder anterior: 1=Arganzuela, 2=Chamberí, 3=Fuencarral, 4=Latina, 5=Usera
        $centroIds = Centro::pluck('id')->toArray();
        // IDs de profesionales ficticios (seedéalos si no existen, e.g., en ProfesionalesSeeder)
        $profesionalIds = [1, 2, 3, 4]; // Ej: 1=Director Genérico A, etc.

        $directores = [
            // Director actual en Arganzuela (ID 1)
            [
                'profesional_id' => $profesionalIds[0] ?? 1,
                'centro_id' => $centroIds[0] ?? 1,
                'fecha_alta' => '2023-01-15',
                'fecha_baja' => null, // Actual
            ],
            // Cambio histórico en Chamberí: Director anterior (baja en 2024), actual (ID 2)
            [
                'profesional_id' => $profesionalIds[1] ?? 2,
                'centro_id' => $centroIds[1] ?? 2,
                'fecha_alta' => '2022-06-01',
                'fecha_baja' => '2024-03-10', // Histórico
            ],
            [
                'profesional_id' => $profesionalIds[0] ?? 1,
                'centro_id' => $centroIds[1] ?? 2,
                'fecha_alta' => '2024-03-11',
                'fecha_baja' => null, // Actual
            ],
            // Director en Fuencarral (ID 3)
            [
                'profesional_id' => $profesionalIds[2] ?? 3,
                'centro_id' => $centroIds[2] ?? 3,
                'fecha_alta' => '2023-09-20',
                'fecha_baja' => null,
            ],
            // Director en Latina (ID 4), con baja futura para simular
            [
                'profesional_id' => $profesionalIds[3] ?? 4,
                'centro_id' => $centroIds[3] ?? 4,
                'fecha_alta' => '2024-01-05',
                'fecha_baja' => null,
            ],
            // No asignar a Usera (ID 5) para variedad (director_id null en centro)
        ];

        foreach ($directores as $directorData) {
            // Verifica unique constraint y crea si no existe
            Director::firstOrCreate(
                [
                    'profesional_id' => $directorData['profesional_id'],
                    'centro_id' => $directorData['centro_id'],
                    'fecha_alta' => $directorData['fecha_alta'],
                ],
                $directorData
            );
        }

        // Actualiza centros.director_id para apuntar al actual (sin fecha_baja)
        foreach (Centro::all() as $centro) {
            $directorActual = Director::where('centro_id', $centro->id)
                ->whereNull('fecha_baja')
                ->whereNull('deleted_at')
                ->first();
            if ($directorActual) {
                $centro->update(['director_id' => $directorActual->id]);
            }
        }
    }
}
