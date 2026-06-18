<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Factory para TipoPlan en tests. Genera tipos eliminables por defecto.
 */
class TipoPlanFactory extends Factory
{
    protected $model = TipoPlan::class;

    /**
     * Define el estado por defecto de un TipoPlan de test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug'        => $this->faker->unique()->slug(2),
            'nombre'      => 'Plan ' . $this->faker->words(3, true),
            'ambito'      => $this->faker->randomElement(['asp', 'especializado']),
            'descripcion' => $this->faker->sentence(),
            'activo'      => true,
            'eliminable'  => true,
        ];
    }

    /**
     * Tipo de ámbito ASP.
     *
     * @return static
     */
    public function asp(): static
    {
        return $this->state(['ambito' => 'asp']);
    }

    /**
     * Tipo de ámbito especializado.
     *
     * @return static
     */
    public function especializado(): static
    {
        return $this->state(['ambito' => 'especializado']);
    }

    /**
     * Tipo no eliminable (como los del seeder de sistema).
     *
     * @return static
     */
    public function noEliminable(): static
    {
        return $this->state(['eliminable' => false]);
    }
}
