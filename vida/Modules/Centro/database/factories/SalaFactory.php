<?php

namespace Modules\Centro\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;

/**
 * Factory para la entidad Sala.
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'centro_id'   => Centro::factory(),
            'nombre'      => fake()->randomElement(['Aula A', 'Sala de reuniones', 'Despacho 1', 'Sala polivalente', 'Sala multiusos']),
            'descripcion' => fake()->optional()->sentence(),
            'capacidad'   => fake()->optional()->numberBetween(5, 50),
            'accesible'   => fake()->boolean(70),
            'activa'      => true,
            'notas'       => null,
        ];
    }

    /** Estado sala inactiva. */
    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }
}
