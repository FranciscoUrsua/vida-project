<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profesional;
use App\Models\Titulacion;

class ProfesionalesSeeder extends Seeder
{
    public function run(): void
    {
        $titulacionIds = Titulacion::pluck('id')->toArray(); // Usa las seedeadas (Grado TS primero)

        $profesionales = [
            [
                'nombre' => 'Ana',
                'apellido1' => 'García',
                'apellido2' => 'López',
                'tipo_id' => 'DNI',
                'numero_id' => '12345678Z',
                'email' => 'ana.garcia@madrid.es',
                'telefono' => '+34 912 345 678',
                'titulacion_id' => $titulacionIds[0] ?? 1, // Grado en Trabajo Social
            ],
            [
                'nombre' => 'Carlos',
                'apellido1' => 'Martínez',
                'apellido2' => 'Rodríguez',
                'tipo_id' => 'DNI',
                'numero_id' => '87654321Y',
                'email' => 'carlos.martinez@madrid.es',
                'telefono' => '+34 912 345 679',
                'titulacion_id' => $titulacionIds[1] ?? 2, // Grado en Psicología
            ],
            [
                'nombre' => 'María',
                'apellido1' => 'Pérez',
                'apellido2' => 'Sánchez',
                'tipo_id' => 'DNI',
                'numero_id' => '11223344X',
                'email' => 'maria.perez@madrid.es',
                'telefono' => '+34 912 345 680',
                'titulacion_id' => $titulacionIds[2] ?? 3, // Grado en Educación Social
            ],
            [
                'nombre' => 'David',
                'apellido1' => 'López',
                'apellido2' => null,
                'tipo_id' => 'NIE',
                'numero_id' => 'X9876543',
                'email' => 'david.lopez@madrid.es',
                'telefono' => '+34 912 345 681',
                'titulacion_id' => $titulacionIds[3] ?? 4, // Grado en Derecho
            ],
            [
                'nombre' => 'Laura',
                'apellido1' => 'Gómez',
                'apellido2' => 'Fernández',
                'tipo_id' => 'DNI',
                'numero_id' => '55667788W',
                'email' => 'laura.gomez@madrid.es',
                'telefono' => '+34 912 345 682',
                'titulacion_id' => $titulacionIds[4] ?? 5, // Máster en Intervención Social
            ],
        ];

        foreach ($profesionales as $prof) {
            Profesional::firstOrCreate(
                ['numero_id' => $prof['numero_id']],
                $prof
            );
        }
    }
}
