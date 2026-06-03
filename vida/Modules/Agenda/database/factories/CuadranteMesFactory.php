<?php

namespace Modules\Agenda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Centro\Models\Centro;

class CuadranteMesFactory extends Factory
{
    protected $model = CuadranteMes::class;

    public function definition(): array
    {
        return [
            'centro_id' => fn () => Centro::create([
                'nombre' => 'Centro '.fake()->unique()->numerify('###'),
                'tipo_gestion' => 'municipal_directo',
                'fecha_alta' => now()->toDateString(),
            ])->id,
            'anyo' => now()->year,
            'mes' => now()->month,
            'estado' => EstadoCuadrante::Borrador->value,
            'generado_con_ia' => false,
            'generado_automaticamente' => false,
            'publicado_en' => null,
            'publicado_por_id' => null,
        ];
    }

    public function publicado(): static
    {
        return $this->state([
            'estado' => EstadoCuadrante::Publicado->value,
            'publicado_en' => now(),
        ]);
    }

    public function automatico(): static
    {
        return $this->state([
            'estado' => EstadoCuadrante::Publicado->value,
            'generado_automaticamente' => true,
            'publicado_en' => now(),
        ]);
    }
}
