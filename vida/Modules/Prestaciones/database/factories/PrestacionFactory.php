<?php

namespace Modules\Prestaciones\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Factory de Prestacion para tests — genera prestaciones mínimas válidas.
 *
 * @extends Factory<Prestacion>
 */
class PrestacionFactory extends Factory
{
    /** @var class-string<Prestacion> */
    protected $model = Prestacion::class;

    /**
     * Estado por defecto: prestación de servicio activa.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->bothify('PRE-####')),
            'nombre' => $this->faker->sentence(4),
            'tipo_prestacion' => 'servicio',
            'nivel_garantia' => 'condicionada',
            'activa' => true,
        ];
    }

    /**
     * Prestación de tipo económica.
     */
    public function economica(): static
    {
        return $this->state(['tipo_prestacion' => 'economica']);
    }

    /**
     * Prestación garantizada.
     */
    public function garantizada(): static
    {
        return $this->state(['nivel_garantia' => 'garantizada']);
    }
}
