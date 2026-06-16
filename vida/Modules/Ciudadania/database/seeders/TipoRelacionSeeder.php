<?php

namespace Modules\Ciudadania\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ciudadania\Models\TipoRelacion;

/**
 * Carga el catálogo inicial de tipos de relación entre ciudadanos.
 *
 * Idempotente: usa updateOrCreate con el slug como clave. Los tipos del
 * seeder tienen `eliminable = false` y no pueden borrarse desde backoffice.
 */
class TipoRelacionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['slug' => 'padre',             'etiqueta' => 'Padre/Madre',          'etiqueta_reciproca' => 'Hijo/a',               'slug_reciproco' => 'hijo',              'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'hijo',              'etiqueta' => 'Hijo/a',               'etiqueta_reciproca' => 'Padre/Madre',           'slug_reciproco' => 'padre',             'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'conyuge',           'etiqueta' => 'Cónyuge',              'etiqueta_reciproca' => 'Cónyuge',               'slug_reciproco' => null,                'simetrica' => true,  'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'pareja_de_hecho',   'etiqueta' => 'Pareja de hecho',      'etiqueta_reciproca' => 'Pareja de hecho',       'slug_reciproco' => null,                'simetrica' => true,  'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'hermano',           'etiqueta' => 'Hermano/a',            'etiqueta_reciproca' => 'Hermano/a',             'slug_reciproco' => null,                'simetrica' => true,  'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'abuelo',            'etiqueta' => 'Abuelo/a',             'etiqueta_reciproca' => 'Nieto/a',               'slug_reciproco' => 'nieto',             'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'nieto',             'etiqueta' => 'Nieto/a',              'etiqueta_reciproca' => 'Abuelo/a',              'slug_reciproco' => 'abuelo',            'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'tutor_legal',       'etiqueta' => 'Tutor/a legal',        'etiqueta_reciproca' => 'Tutelado/a',            'slug_reciproco' => 'tutelado',          'simetrica' => false, 'implicacion_funcional' => 'tutor_legal',       'eliminable' => false],
            ['slug' => 'tutelado',          'etiqueta' => 'Tutelado/a',           'etiqueta_reciproca' => 'Tutor/a legal',         'slug_reciproco' => 'tutor_legal',       'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'representante',     'etiqueta' => 'Representante',        'etiqueta_reciproca' => 'Representado/a',        'slug_reciproco' => 'representado',      'simetrica' => false, 'implicacion_funcional' => 'representante',     'eliminable' => false],
            ['slug' => 'representado',      'etiqueta' => 'Representado/a',       'etiqueta_reciproca' => 'Representante',         'slug_reciproco' => 'representante',     'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'cuidador_principal','etiqueta' => 'Cuidador/a principal', 'etiqueta_reciproca' => 'Persona cuidada',       'slug_reciproco' => 'persona_cuidada',   'simetrica' => false, 'implicacion_funcional' => 'cuidador_principal','eliminable' => false],
            ['slug' => 'persona_cuidada',   'etiqueta' => 'Persona cuidada',      'etiqueta_reciproca' => 'Cuidador/a principal',  'slug_reciproco' => 'cuidador_principal','simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'acogedor',          'etiqueta' => 'Acogedor/a',           'etiqueta_reciproca' => 'Acogido/a',             'slug_reciproco' => 'acogido',           'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
            ['slug' => 'acogido',           'etiqueta' => 'Acogido/a',            'etiqueta_reciproca' => 'Acogedor/a',            'slug_reciproco' => 'acogedor',          'simetrica' => false, 'implicacion_funcional' => null,                'eliminable' => false],
        ];

        foreach ($tipos as $datos) {
            TipoRelacion::updateOrCreate(['slug' => $datos['slug']], $datos);
        }
    }
}
