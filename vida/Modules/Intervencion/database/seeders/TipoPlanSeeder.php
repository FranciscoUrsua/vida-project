<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Centro\Models\TipoCentro;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Carga los tipos de plan iniciales del sistema.
 *
 * Todos los tipos del seeder tienen eliminable=false para protegerlos
 * de borrados accidentales desde el backoffice.
 *
 * Los datos no incluyen 'admite_entrada_directa' a propósito: ese campo se
 * configura desde el backoffice (o en la migración que marca el tipo 'pia'),
 * y updateOrCreate no debe devolverlo a false en una re-ejecución del seeder.
 *
 * Plan principal (fase 3, F3-1): `asp_general` se marca como principal solo si no
 * hay ya un tipo principal (en staging lo es `piso`, marcado por la migración), para
 * no chocar con el índice único. `asp_psicologico` y `asp_socioeducativo` son tipos
 * de ASP de ejemplo, no principales, ofrecidos por el tipo de centro `css_general`.
 */
class TipoPlanSeeder extends Seeder
{
    /**
     * Inserta o actualiza los tipos de plan base del sistema.
     *
     * @return void
     */
    public function run(): void
    {
        $tipos = [
            [
                'slug' => 'asp_general',
                'nombre' => 'Plan de Intervención Social (ASP)',
                'ambito' => 'asp',
                'descripcion' => 'Plan de intervención integral gestionado por el TSR de Atención Social Primaria.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'asp_psicologico',
                'nombre' => 'Plan de intervención psicológica',
                'ambito' => 'asp',
                'descripcion' => 'Plan de ASP del psicólogo del centro, con su propio responsable.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'asp_socioeducativo',
                'nombre' => 'Plan socioeducativo',
                'ambito' => 'asp',
                'descripcion' => 'Plan de ASP del educador del centro, con su propio responsable.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'esp_familia_infancia',
                'nombre' => 'Plan de Atención a Familia e Infancia',
                'ambito' => 'especializado',
                'descripcion' => 'Plan especializado para intervención con familias y menores.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'esp_violencia_genero',
                'nombre' => 'Plan de Atención a Víctimas de Violencia de Género',
                'ambito' => 'especializado',
                'descripcion' => 'Plan especializado para intervención con víctimas de violencia de género.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'esp_mayores',
                'nombre' => 'Plan de Atención a Personas Mayores',
                'ambito' => 'especializado',
                'descripcion' => 'Plan especializado para atención a personas mayores en situación de vulnerabilidad.',
                'activo' => true,
                'eliminable' => false,
            ],
            [
                'slug' => 'esp_inclusion',
                'nombre' => 'Plan de Inclusión Social',
                'ambito' => 'especializado',
                'descripcion' => 'Plan especializado para intervención en situaciones de exclusión social.',
                'activo' => true,
                'eliminable' => false,
            ],
        ];

        foreach ($tipos as $datos) {
            TipoPlan::updateOrCreate(['slug' => $datos['slug']], $datos);
        }

        if (TipoPlan::principal() === null) {
            TipoPlan::where('slug', 'asp_general')->update(['es_principal' => true]);
        }

        $this->ofrecerEnCss(['asp_psicologico', 'asp_socioeducativo']);
    }

    /**
     * Añade los tipos de plan indicados al catálogo del tipo de centro `css_general`, si existe.
     *
     * @param list<string> $slugs
     *
     * @return void
     */
    private function ofrecerEnCss(array $slugs): void
    {
        $tipoCentro = TipoCentro::where('codigo', 'css_general')->first();

        if ($tipoCentro === null) {
            return;
        }

        $tipoCentro->tiposPlan()->syncWithoutDetaching(TipoPlan::whereIn('slug', $slugs)->pluck('id'));
    }
}
