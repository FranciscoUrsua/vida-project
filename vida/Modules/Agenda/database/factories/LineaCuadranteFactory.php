<?php

namespace Modules\Agenda\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\LineaCuadrante;
use Modules\Centro\Models\Centro;

class LineaCuadranteFactory extends Factory
{
    protected $model = LineaCuadrante::class;

    public function definition(): array
    {
        return [
            'cuadrante_mes_id' => CuadranteMesFactory::new(),
            'usuario_id'       => User::factory(),
            'centro_id'        => fn () => Centro::create([
                'nombre'       => 'Centro ' . fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta'   => now()->toDateString(),
            ])->id,
            'fecha'            => now()->addDay()->toDateString(),
            'franjas'          => [
                ['inicio' => '09:00', 'fin' => '14:00'],
                ['inicio' => '15:00', 'fin' => '19:00'],
            ],
            'anulada'          => false,
            'excepcion_id'     => null,
        ];
    }
}
