<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Models\AreaEncargo;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Áreas iniciales del encargo profesional (EA-1 de docs/instrucciones-cli/2026-11-encargo-actuaciones.md):
 * jurídica, psicológica y formativa, con alguna herramienta de los catálogos base si existe.
 *
 * Idempotente: crea el área por código si no está y añade las herramientas sin quitar las que
 * se hayan configurado en el backoffice. La llama la migración de las áreas (para que lleguen a
 * staging con el despliegue) y DatabaseSeeder (en una instalación nueva, las fichas y escalas
 * se siembran después de migrar). No asocia plantillas de informe: las de ejemplo leen la
 * Historia Social y un área no las admite (EA-9).
 */
class AreasEncargoSeeder extends Seeder
{
    /**
     * Áreas iniciales: código => [nombre, descripción, fichas por nombre, escalas por código].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>, 3: list<string>}>
     */
    private const AREAS = [
        'juridica' => ['Jurídica', 'Orientación y asesoramiento legal: vivienda, extranjería, familia, violencia de género.', ['Situación de vivienda', 'Situación económica'], []],
        'psicologica' => ['Psicológica', 'Valoración y apoyo psicológico.', [], ['pfeiffer_spmsq']],
        'formativa' => ['Formativa', 'Orientación formativa y para el empleo.', ['Valoración social libre'], []],
    ];

    /**
     * Crea las áreas y les añade las herramientas que existan.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (self::AREAS as $codigo => [$nombre, $descripcion, $fichas, $escalas]) {
            $area = AreaEncargo::withTrashed()->firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'activa' => true],
            );

            $area->tiposFicha()->syncWithoutDetaching(TipoFicha::whereIn('nombre', $fichas)->pluck('id')->all());
            $area->tiposEscala()->syncWithoutDetaching(TipoEscala::whereIn('codigo', $escalas)->pluck('id')->all());
        }
    }
}
