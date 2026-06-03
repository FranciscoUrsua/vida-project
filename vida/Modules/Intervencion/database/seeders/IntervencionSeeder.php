<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\TipoValoracionFicha;

/**
 * Seeder del módulo Intervención.
 *
 * Crea la configuración mínima de fichas y tipos de valoración necesaria
 * para que el módulo sea funcional en un centro nuevo:
 * - 3 tipos de ficha (situación económica, red de apoyo, vivienda)
 * - 1 tipo de valoración ASP que agrupa las tres fichas
 * - 3 registros pivot tipo_valoracion_fichas con orden y obligatoriedad
 */
class IntervencionSeeder extends Seeder
{
    public function run(): void
    {
        $fichaEconomica = TipoFicha::firstOrCreate(
            ['nombre' => 'Situación económica'],
            [
                'descripcion' => 'Ingresos, gastos y deudas del hogar.',
                'schema' => [
                    ['nombre' => 'ingresos_mensuales_hogar',      'tipo' => 'number',  'obligatorio' => true,  'orden' => 1],
                    ['nombre' => 'numero_personas_dependientes',  'tipo' => 'number',  'obligatorio' => false, 'orden' => 2],
                    ['nombre' => 'tiene_deudas',                  'tipo' => 'boolean', 'obligatorio' => false, 'orden' => 3],
                ],
                'activo' => true,
            ]
        );

        $fichaRedApoyo = TipoFicha::firstOrCreate(
            ['nombre' => 'Red de apoyo social'],
            [
                'descripcion' => 'Familia, amigos y entidades de soporte.',
                'schema' => [
                    ['nombre' => 'convivencia',        'tipo' => 'select',  'obligatorio' => false, 'orden' => 1],
                    ['nombre' => 'apoyo_familiar',     'tipo' => 'boolean', 'obligatorio' => false, 'orden' => 2],
                    ['nombre' => 'aislamiento_social', 'tipo' => 'boolean', 'obligatorio' => false, 'orden' => 3],
                ],
                'activo' => true,
            ]
        );

        $fichaVivienda = TipoFicha::firstOrCreate(
            ['nombre' => 'Situación de vivienda'],
            [
                'descripcion' => 'Condiciones del domicilio y régimen de tenencia.',
                'schema' => [
                    ['nombre' => 'regimen_tenencia',    'tipo' => 'select', 'obligatorio' => true,  'orden' => 1],
                    ['nombre' => 'estado_conservacion', 'tipo' => 'select', 'obligatorio' => false, 'orden' => 2],
                ],
                'activo' => true,
            ]
        );

        $tipoValoracion = TipoValoracion::firstOrCreate(
            ['nombre' => 'Valoración ASP inicial'],
            [
                'contexto' => 'ASP',
                'descripcion' => 'Valoración de primera atención en Atención Social Primaria.',
                'activo' => true,
            ]
        );

        TipoValoracionFicha::firstOrCreate(
            ['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $fichaEconomica->id],
            ['orden' => 0, 'obligatoria' => true]
        );

        TipoValoracionFicha::firstOrCreate(
            ['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $fichaRedApoyo->id],
            ['orden' => 1, 'obligatoria' => false]
        );

        TipoValoracionFicha::firstOrCreate(
            ['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $fichaVivienda->id],
            ['orden' => 2, 'obligatoria' => false]
        );
    }
}
