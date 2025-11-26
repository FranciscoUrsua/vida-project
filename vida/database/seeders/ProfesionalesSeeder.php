<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profesional;
use App\Models\Titulacion;

class ProfesionalesSeeder extends Seeder
{
    public function run(): void
    {
        $titulacionIds = Titulacion::pluck('id')->toArray();

        $profesionales = [
            [
                'nombre' => 'Ana',
                'apellido1' => 'García',
                'apellido2' => 'López',
                'tipo_documento' => 'DNI', // CAMBIADO: tipo_documento
                'numero_id' => '12345678Z', // Válido
                'email' => 'ana.garcia@madrid.es',
                'telefono' => '+34 912 345 678',
                'sexo' => 'F',
                'titulacion_id' => $titulacionIds[0] ?? 1,
            ],
            [
                'nombre' => 'Carlos',
                'apellido1' => 'Martínez',
                'apellido2' => 'Rodríguez',
                'tipo_documento' => 'DNI',
                'numero_id' => '27960822K', // Válido
                'email' => 'carlos.martinez@madrid.es',
                'telefono' => '+34 912 345 679',
                'sexo' => 'M',
                'titulacion_id' => $titulacionIds[1] ?? 2,
            ],
            [
                'nombre' => 'María',
                'apellido1' => 'Pérez',
                'apellido2' => 'Sánchez',
                'tipo_documento' => 'NIE',
                'numero_id' => 'Y1439277C', // Válido
                'email' => 'maria.perez@madrid.es',
                'telefono' => '+34 912 345 680',
                'sexo' => 'F',
                'titulacion_id' => $titulacionIds[2] ?? 3,
            ],
            [
                'nombre' => 'David',
                'apellido1' => 'López',
                'apellido2' => null,
                'tipo_documento' => 'DNI',
                'numero_id' => '69818411W', // Válido
                'email' => 'david.lopez@madrid.es',
                'telefono' => '+34 912 345 681',
                'sexo' => 'M',
                'titulacion_id' => $titulacionIds[3] ?? 4,
            ],
            [
                'nombre' => 'Laura',
                'apellido1' => 'Gómez',
                'apellido2' => 'Fernández',
                'tipo_documento' => 'PASAPORTE',
                'numero_id' => 'ABC123456', // Válido
                'email' => 'laura.gomez@madrid.es',
                'telefono' => '+34 912 345 682',
                'sexo' => 'F',
                'titulacion_id' => $titulacionIds[4] ?? 5,
            ],
        ];

        foreach ($profesionales as $profData) {
            $prof = Profesional::firstOrCreate(
                ['numero_id' => $profData['numero_id']],
                $profData
            );
            $prof->save(); // Fuerza trigger boot
        }
    }
}
