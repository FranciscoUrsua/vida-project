<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\Cargo;

/**
 * Seeder del catálogo de cargos profesionales.
 *
 * Carga los cargos habituales en los Servicios Sociales del
 * Ayuntamiento de Madrid. Configurable desde el backoffice.
 *
 * Refleja el catálogo revisado en la BD compartida el 2026-09-25 (slugs cortos
 * y 9 cargos). Busca por slug: cambiar un slug aquí crearía un cargo duplicado
 * en las instalaciones existentes.
 */
class CargosSeeder extends Seeder
{
    /**
     * Crea o actualiza cada cargo del catálogo por su slug.
     *
     * @return void
     */
    public function run(): void
    {
        $cargos = [
            ['slug' => 'ts',                   'nombre' => 'Trabajador/a Social',            'descripcion' => null],
            ['slug' => 'psicologo',            'nombre' => 'Psicólogo/a',                    'descripcion' => null],
            ['slug' => 'educadorsocial',       'nombre' => 'Educador/a Social',              'descripcion' => null],
            ['slug' => 'terapeutaocupacional', 'nombre' => 'Terapeuta Ocupacional',          'descripcion' => null],
            ['slug' => 'auxss',                'nombre' => 'Auxiliar de Servicios Sociales', 'descripcion' => 'Auxiliar para información sobre prestaciones'],
            ['slug' => 'abogado',              'nombre' => 'Abogado/a',                      'descripcion' => 'Servicio de Orientación Jurídica (SOJ)'],
            ['slug' => 'coordinador',          'nombre' => 'Coordinador/a de Centro',        'descripcion' => null],
            ['slug' => 'administrativo',       'nombre' => 'Administrativo/a',               'descripcion' => null],
            ['slug' => 'auxadmin',             'nombre' => 'Auxiliar Administrativo/a',      'descripcion' => null],
        ];

        foreach ($cargos as $cargo) {
            Cargo::updateOrCreate(
                ['slug' => $cargo['slug']],
                ['nombre' => $cargo['nombre'], 'descripcion' => $cargo['descripcion'], 'activo' => true]
            );
        }
    }
}
