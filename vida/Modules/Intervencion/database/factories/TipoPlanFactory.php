<?php

namespace Modules\Intervencion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Factory para TipoPlan en tests. Genera tipos eliminables por defecto.
 *
 * @extends Factory<TipoPlan>
 */
class TipoPlanFactory extends Factory
{
    protected $model = TipoPlan::class;

    /**
     * Define el estado por defecto de un TipoPlan de test.
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'nombre' => 'Plan '.$this->faker->words(3, true),
            'ambito' => $this->faker->randomElement(['asp', 'especializado']),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
            'eliminable' => true,
        ];
    }

    /**
     * Tipo de ámbito ASP.
     */
    public function asp(): static
    {
        return $this->state(['ambito' => 'asp']);
    }

    /**
     * Tipo de ámbito especializado.
     */
    public function especializado(): static
    {
        return $this->state(['ambito' => 'especializado']);
    }

    /**
     * Tipo especializado que admite entrada directa (planes sin plan ASP previo), como el PIA del CIAM.
     */
    public function entradaDirecta(): static
    {
        return $this->state(['ambito' => 'especializado', 'admite_entrada_directa' => true]);
    }

    /**
     * Tipo no eliminable (como los del seeder de sistema).
     */
    public function noEliminable(): static
    {
        return $this->state(['eliminable' => false]);
    }
}
