<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Carga los tipos de plan iniciales del sistema.
 *
 * Todos los tipos del seeder tienen eliminable=false para protegerlos
 * de borrados accidentales desde el backoffice.
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
                'slug'        => 'asp_general',
                'nombre'      => 'Plan de Intervención Social (ASP)',
                'ambito'      => 'asp',
                'descripcion' => 'Plan de intervención integral gestionado por el TSR de Atención Social Primaria.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_familia_infancia',
                'nombre'      => 'Plan de Atención a Familia e Infancia',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención con familias y menores.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_violencia_genero',
                'nombre'      => 'Plan de Atención a Víctimas de Violencia de Género',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención con víctimas de violencia de género.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_mayores',
                'nombre'      => 'Plan de Atención a Personas Mayores',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para atención a personas mayores en situación de vulnerabilidad.',
                'activo'      => true,
                'eliminable'  => false,
            ],
            [
                'slug'        => 'esp_inclusion',
                'nombre'      => 'Plan de Inclusión Social',
                'ambito'      => 'especializado',
                'descripcion' => 'Plan especializado para intervención en situaciones de exclusión social.',
                'activo'      => true,
                'eliminable'  => false,
            ],
        ];

        foreach ($tipos as $datos) {
            TipoPlan::updateOrCreate(['slug' => $datos['slug']], $datos);
        }
    }
}
