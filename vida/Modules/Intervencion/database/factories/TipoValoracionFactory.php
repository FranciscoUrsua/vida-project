<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\TipoValoracion;

/**
 * @extends Factory<TipoValoracion>
 */
class TipoValoracionFactory extends Factory
{
    protected $model = TipoValoracion::class;

    public function definition(): array
    {
        return [
            'nombre'      => fake()->words(3, true),
            'contexto'    => fake()->randomElement(['ASP', 'especializada_mayores', 'especializada_familia']),
            'descripcion' => fake()->optional()->sentence(),
            'activo'      => true,
        ];
    }
}
