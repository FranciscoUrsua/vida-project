<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Models\Valoracion;

/**
 * @extends Factory<Ficha>
 */
class FichaFactory extends Factory
{
    protected $model = Ficha::class;

    public function definition(): array
    {
        return [
            'valoracion_id' => Valoracion::factory(),
            'tipo_ficha_id' => TipoFicha::factory(),
            'datos' => null,
            'notas' => null,
            'completada' => false,
        ];
    }

    /**
     * Ficha completada con datos.
     */
    public function completada(): static
    {
        return $this->state([
            'datos' => ['campo_prueba' => fake()->sentence()],
            'completada' => true,
        ]);
    }
}
