<?php

namespace Modules\Ciudadania\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ciudadania\Models\UnidadConvivencia;

/**
 * @extends Factory<UnidadConvivencia>
 */
class UnidadConvivenciaFactory extends Factory
{
    protected $model = UnidadConvivencia::class;

    public function definition(): array
    {
        return [
            'domicilio'          => $this->faker->streetAddress(),
            'latitud'            => $this->faker->latitude(40.30, 40.65),
            'longitud'           => $this->faker->longitude(-3.83, -3.52),
            'fecha_constitucion' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'fecha_disolucion'   => null,
            'observaciones'      => null,
        ];
    }

    /** Estado: UC disuelta con fecha de disolución en el pasado. */
    public function disuelta(): static
    {
        return $this->state(fn () => [
            'fecha_disolucion' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ]);
    }
}
