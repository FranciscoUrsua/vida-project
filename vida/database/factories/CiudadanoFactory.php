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
            'nombre' => fake()->firstName(),
            'apellido1' => fake()->lastName(),
            'apellido2' => fake()->optional()->lastName(),
            'fecha_nacimiento' => fake()->date('Y-m-d', '-18 years'),
            'sexo' => fake()->randomElement(['hombre', 'mujer', 'otro']),
            'nivel_identificacion' => 'basico',
            'activo' => true,
            'es_vvg' => false,
            'es_psh' => false,
            'colectivo_extra_protegido' => false,
        ];
    }

    /**
     * Ciudadano marcado como Víctima de Violencia de Género.
     * Sus campos de dirección se suprimen en extracciones de Nivel 2 y 3.
     */
    public function vvg(): static
    {
        return $this->state([
            'es_vvg' => true,
            'direccion_normalizada' => true,
            'nombre_via' => fake()->streetName(),
            'codigo_postal' => '28'.fake()->numerify('###'),
        ]);
    }

    /**
     * Persona Sin Hogar — sin dirección postal, con zona de intervención.
     */
    public function psh(): static
    {
        return $this->state([
            'es_psh' => true,
            'nivel_identificacion' => 'no_identificado',
            'zona_intervencion' => fake()->randomElement(['Centro', 'Lavapiés', 'Malasaña', 'Carabanchel']),
            'pernocta_lat' => fake()->latitude(40.31, 40.53),
            'pernocta_lng' => fake()->longitude(-3.83, -3.52),
        ]);
    }

    /**
     * Ciudadano con dirección geocodificada y normalizada.
     */
    public function conDireccionNormalizada(): static
    {
        return $this->state([
            'direccion_normalizada' => true,
            'origen_direccion' => 'profesional',
            'tipo_via' => 'Calle',
            'nombre_via' => fake()->streetName(),
            'tipo_numeracion' => 'numero',
            'numero' => (string) fake()->buildingNumber(),
            'portal' => null,
            'escalera' => null,
            'piso' => fake()->randomElement(['1', '2', '3', null]),
            'puerta' => fake()->randomElement(['izq', 'dcha', null]),
            'codigo_postal' => '28'.fake()->numerify('###'),
            'municipio' => 'Madrid',
        ]);
    }

    /**
     * Ciudadano con dirección introducida manualmente pero no normalizada.
     */
    public function sinDireccionNormalizada(): static
    {
        return $this->state([
            'direccion_normalizada' => false,
            'direccion_texto' => fake()->address(),
        ]);
    }
}
