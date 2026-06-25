<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\Cargo;

/**
 * Seeder del catálogo de cargos profesionales.
 *
 * Carga los cargos habituales en los Servicios Sociales del
 * Ayuntamiento de Madrid. Configurable desde el backoffice.
 */
class CargosSeeder extends Seeder
{
    public function run(): void
    {
        $cargos = [
            ['slug' => 'trabajador-social',          'nombre' => 'Trabajador/a Social',               'descripcion' => 'Técnico/a superior de intervención social directa'],
            ['slug' => 'psicologo',                  'nombre' => 'Psicólogo/a',                       'descripcion' => null],
            ['slug' => 'educador-social',            'nombre' => 'Educador/a Social',                  'descripcion' => null],
            ['slug' => 'terapeuta-ocupacional',      'nombre' => 'Terapeuta Ocupacional',              'descripcion' => null],
            ['slug' => 'auxiliar-servicios-sociales','nombre' => 'Auxiliar de Servicios Sociales',    'descripcion' => 'Auxiliar de apoyo en atención a domicilio y centros'],
            ['slug' => 'abogado',                    'nombre' => 'Abogado/a',                         'descripcion' => 'Servicio de Orientación Jurídica (SOJ)'],
            ['slug' => 'tecnico-integracion-social', 'nombre' => 'Técnico/a de Integración Social',   'descripcion' => null],
            ['slug' => 'mediador-social',            'nombre' => 'Mediador/a Social',                  'descripcion' => null],
            ['slug' => 'coordinador-centro',         'nombre' => 'Coordinador/a de Centro',           'descripcion' => null],
            ['slug' => 'tecnico-acogida',            'nombre' => 'Técnico/a de Acogida',              'descripcion' => null],
            ['slug' => 'administrativo',             'nombre' => 'Administrativo/a',                  'descripcion' => null],
            ['slug' => 'auxiliar-administrativo',    'nombre' => 'Auxiliar Administrativo/a',         'descripcion' => null],
            ['slug' => 'ordenanza',                  'nombre' => 'Ordenanza',                         'descripcion' => null],
        ];

        foreach ($cargos as $cargo) {
            Cargo::updateOrCreate(
                ['slug' => $cargo['slug']],
                ['nombre' => $cargo['nombre'], 'descripcion' => $cargo['descripcion'], 'activo' => true]
            );
        }
    }
}
