<?php

namespace Database\Factories;

use App\Models\Ciudadano;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciudadano>
 */
class CiudadanoFactory extends Factory
{
    protected $model = Ciudadano::class;

    public function definition(): array
    {
        return [
            'nombre'              => fake()->firstName(),
            'apellido1'           => fake()->lastName(),
            'apellido2'           => fake()->optional()->lastName(),
            'fecha_nacimiento'    => fake()->date('Y-m-d', '-18 years'),
            'sexo'                => fake()->randomElement(['hombre', 'mujer', 'otro']),
            'nivel_identificacion' => 'basico',
            'activo'              => true,
        ];
    }
}
