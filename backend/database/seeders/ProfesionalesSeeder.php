<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profesional;
use App\Models\Titulacion;
use App\Models\Distrito; // No necesario, pero si agregas georeferenciación futura

class ProfesionalesSeeder extends Seeder
{
    public function run(): void
    {
        $titulacionIds = Titulacion::pluck('id')->toArray(); // Usa las seedeadas

        $profesionales = [
            [
                'nombre' => 'Ana',
                'apellido1' => 'García',
                'apellido2' => 'López',
                'tipo_id' => 'DNI',
                'numero_id' => '12345678Z', // VÁLIDO DNI (checksum Z)
                'email' => 'ana.garcia@madrid.es',
                'telefono' => '+34 912 345 678',
                'sexo' => 'F',
                'titulacion_id' => $titulacionIds[0] ?? 1, // Grado en Trabajo Social
            ],
            [
                'nombre' => 'Carlos',
                'apellido1' => 'Martínez',
                'apellido2' => 'Rodríguez',
                'tipo_id' => 'DNI',
                'numero_id' => '87654321R', // VÁLIDO DNI (checksum R)
                'email' => 'carlos.martinez@madrid.es',
                'telefono' => '+34 912 345 679',
                'sexo' => 'M',
                'titulacion_id' => $titulacionIds[1] ?? 2, // Grado en Psicología
            ],
            [
                'nombre' => 'María',
                'apellido1' => 'Pérez',
                'apellido2' => 'Sánchez',
                'tipo_id' => 'NIE',
                'numero_id' => 'Y1234567T', // VÁLIDO NIE (checksum T)
                'email' => 'maria.perez@madrid.es',
                'telefono' => '+34 912 345 680',
                'sexo' => 'F',
                'titulacion_id' => $titulacionIds[2] ?? 3, // Grado en Educación Social
            ],
            [
                'nombre' => 'David',
                'apellido1' => 'López',
                'apellido2' => null,
                'tipo_id' => 'DNI',
                'numero_id' => '44556677L', // VÁLIDO DNI (checksum L)
                'email' => 'david.lopez@madrid.es',
                'telefono' => '+34 912 345 681',
                'sexo' => 'M',
                'titulacion_id' => $titulacionIds[3] ?? 4, // Grado en Derecho
            ],
            [
                'nombre' => 'Laura',
                'apellido1' => 'Gómez',
                'apellido2' => 'Fernández',
                'tipo_id' => 'Pasaporte',
                'numero_id' => 'ABC123456', // VÁLIDO Pasaporte (3 letras + 6 dígitos)
                'email' => 'laura.gomez@madrid.es',
                'telefono' => '+34 912 345 682',
                'sexo' => 'F',
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
