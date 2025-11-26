<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Titulacion;

class TitulacionesSeeder extends Seeder
{
    public function run(): void
    {
        $titulaciones = [
            [
                'nombre' => 'Grado en Trabajo Social',
                'descripcion' => 'Titulacion principal para intervención social y atención a vulnerables (Ley 44/2007).',
            ],
            [
                'nombre' => 'Grado en Psicología',
                'descripcion' => 'Para evaluaciones psicológicas en casos familiares y de dependencia.',
            ],
            [
                'nombre' => 'Grado en Educación Social',
                'descripcion' => 'Mediación y apoyo educativo en entornos sociales y juveniles.',
            ],
            [
                'nombre' => 'Grado en Derecho',
                'descripcion' => 'Asesoramiento legal en derechos sociales y prestaciones.',
            ],
            [
                'nombre' => 'Máster en Intervención Social',
                'descripcion' => 'Especialización en gestión de servicios sociales y políticas públicas.',
            ],
            [
                'nombre' => 'Grado en Sociología',
                'descripcion' => 'Análisis social y diseño de programas de inclusión.',
            ],
        ];

        foreach ($titulaciones as $titulacion) {
            Titulacion::firstOrCreate(
                ['nombre' => $titulacion['nombre']],
                $titulacion
            );
        }
    }
}
