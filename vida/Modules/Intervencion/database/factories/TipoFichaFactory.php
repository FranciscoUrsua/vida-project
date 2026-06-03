<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\TipoFicha;

/**
 * @extends Factory<TipoFicha>
 */
class TipoFichaFactory extends Factory
{
    protected $model = TipoFicha::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
            'schema' => [
                ['nombre' => 'campo_prueba', 'tipo' => 'text', 'obligatorio' => false, 'orden' => 1],
            ],
            'activo' => true,
        ];
    }

    /**
     * Tipo de ficha inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
