<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\Titulacion;

/**
 * Seeder del catálogo de titulaciones académicas.
 *
 * Carga las titulaciones habituales en los Servicios Sociales.
 * Configurable desde el backoffice.
 */
class TitulacionesSeeder extends Seeder
{
    public function run(): void
    {
        $titulaciones = [
            'Grado / Diplomatura en Trabajo Social',
            'Grado / Licenciatura en Psicología',
            'Grado en Educación Social',
            'Grado / Licenciatura en Pedagogía',
            'Grado / Licenciatura en Derecho',
            'Grado en Terapia Ocupacional',
            'Grado / Licenciatura en Sociología',
            'Grado en Integración Social',
            'CFGS Integración Social',
            'CFGM Atención Sociosanitaria',
            'CFGM Auxiliar de Enfermería',
            'Bachillerato',
            'Graduado en Educación Secundaria (ESO)',
            'Certificado de Escolaridad',
        ];

        foreach ($titulaciones as $nombre) {
            Titulacion::firstOrCreate(
                ['nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
