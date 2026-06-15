<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\TipoValoracion;
use Modules\Intervencion\Models\TipoValoracionFicha;

/**
 * Seeder del módulo Intervención.
 *
 * Delega la creación de fichas a IntervencionFichaSeeder y crea
 * el tipo de valoración ASP inicial con las tres fichas base.
 */
class IntervencionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(IntervencionFichaSeeder::class);

        $fichaEconomica = TipoFicha::where('nombre', 'Situación económica')->first();
        $fichaVivienda  = TipoFicha::where('nombre', 'Situación de vivienda')->first();
        $fichaLibre     = TipoFicha::where('nombre', 'Valoración social libre')->first();

        $tipoValoracion = TipoValoracion::firstOrCreate(
            ['nombre' => 'Valoración ASP inicial'],
            [
                'contexto'    => 'ASP',
                'descripcion' => 'Valoración de primera atención en Atención Social Primaria.',
                'activo'      => true,
            ]
        );

        foreach ([
            [$fichaEconomica, 0, true],
            [$fichaVivienda,  1, false],
            [$fichaLibre,     2, false],
        ] as [$ficha, $orden, $obligatoria]) {
            if ($ficha) {
                TipoValoracionFicha::firstOrCreate(
                    ['tipo_valoracion_id' => $tipoValoracion->id, 'tipo_ficha_id' => $ficha->id],
                    ['orden' => $orden, 'obligatoria' => $obligatoria]
                );
            }
        }
    }
}
