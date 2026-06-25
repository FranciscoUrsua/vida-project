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
            ['slug' => 'trabajo-social',           'nombre' => 'Grado / Diplomatura en Trabajo Social'],
            ['slug' => 'psicologia',               'nombre' => 'Grado / Licenciatura en Psicología'],
            ['slug' => 'educacion-social',         'nombre' => 'Grado en Educación Social'],
            ['slug' => 'pedagogia',                'nombre' => 'Grado / Licenciatura en Pedagogía'],
            ['slug' => 'derecho',                  'nombre' => 'Grado / Licenciatura en Derecho'],
            ['slug' => 'terapia-ocupacional',      'nombre' => 'Grado en Terapia Ocupacional'],
            ['slug' => 'sociologia',               'nombre' => 'Grado / Licenciatura en Sociología'],
            ['slug' => 'integracion-social',       'nombre' => 'Grado en Integración Social'],
            ['slug' => 'cfgs-integracion-social',  'nombre' => 'CFGS Integración Social'],
            ['slug' => 'cfgm-socio-sanitaria',     'nombre' => 'CFGM Atención Sociosanitaria'],
            ['slug' => 'cfgm-enfermeria',          'nombre' => 'CFGM Auxiliar de Enfermería'],
            ['slug' => 'bachillerato',             'nombre' => 'Bachillerato'],
            ['slug' => 'eso',                      'nombre' => 'Graduado en Educación Secundaria (ESO)'],
            ['slug' => 'certificado-escolaridad',  'nombre' => 'Certificado de Escolaridad'],
        ];

        foreach ($titulaciones as $titulacion) {
            Titulacion::updateOrCreate(
                ['slug' => $titulacion['slug']],
                ['nombre' => $titulacion['nombre'], 'activo' => true]
            );
        }
    }
}
