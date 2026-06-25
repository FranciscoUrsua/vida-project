<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usuarios\Models\TipoRelacionProfesional;

/**
 * Seeder del catálogo de tipos de relación profesional.
 *
 * Carga los tipos de vínculo habituales en el Ayuntamiento de Madrid.
 * El campo es_externo=true indica que el campo organizacion del
 * profesional es relevante para ese tipo de relación.
 * Configurable desde el backoffice.
 */
class TiposRelacionProfesionalSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['slug' => 'funcionario-carrera', 'nombre' => 'Funcionario/a de carrera',           'es_externo' => false],
            ['slug' => 'laboral-fijo',        'nombre' => 'Personal laboral fijo/a',            'es_externo' => false],
            ['slug' => 'interino',            'nombre' => 'Personal interino/a',                'es_externo' => false],
            ['slug' => 'eventual',            'nombre' => 'Personal eventual',                   'es_externo' => false],
            ['slug' => 'laboral-temporal',    'nombre' => 'Contratado/a laboral temporal',      'es_externo' => false],
            ['slug' => 'empresa-externa',     'nombre' => 'Empresa externa (contrata pública)', 'es_externo' => true],
            ['slug' => 'voluntario',          'nombre' => 'Voluntario/a',                       'es_externo' => true],
        ];

        foreach ($tipos as $tipo) {
            TipoRelacionProfesional::updateOrCreate(
                ['slug' => $tipo['slug']],
                ['nombre' => $tipo['nombre'], 'es_externo' => $tipo['es_externo'], 'activo' => true]
            );
        }
    }
}
