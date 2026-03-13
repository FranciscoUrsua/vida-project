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
            ['nombre' => 'Trabajador/a Social',               'descripcion' => 'Técnico/a superior de intervención social directa'],
            ['nombre' => 'Psicólogo/a',                       'descripcion' => null],
            ['nombre' => 'Educador/a Social',                  'descripcion' => null],
            ['nombre' => 'Terapeuta Ocupacional',              'descripcion' => null],
            ['nombre' => 'Auxiliar de Servicios Sociales',    'descripcion' => 'Auxiliar de apoyo en atención a domicilio y centros'],
            ['nombre' => 'Abogado/a',                         'descripcion' => 'Servicio de Orientación Jurídica (SOJ)'],
            ['nombre' => 'Técnico/a de Integración Social',   'descripcion' => null],
            ['nombre' => 'Mediador/a Social',                  'descripcion' => null],
            ['nombre' => 'Coordinador/a de Centro',           'descripcion' => null],
            ['nombre' => 'Técnico/a de Acogida',              'descripcion' => null],
            ['nombre' => 'Administrativo/a',                  'descripcion' => null],
            ['nombre' => 'Auxiliar Administrativo/a',         'descripcion' => null],
            ['nombre' => 'Ordenanza',                         'descripcion' => null],
        ];

        foreach ($cargos as $cargo) {
            Cargo::firstOrCreate(
                ['nombre' => $cargo['nombre']],
                ['descripcion' => $cargo['descripcion'], 'activo' => true]
            );
        }
    }
}
