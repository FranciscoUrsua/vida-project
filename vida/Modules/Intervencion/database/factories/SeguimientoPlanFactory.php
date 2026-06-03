<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\SeguimientoPlan;

/**
 * @extends Factory<SeguimientoPlan>
 */
class SeguimientoPlanFactory extends Factory
{
    protected $model = SeguimientoPlan::class;

    public function definition(): array
    {
        return [
            'plan_id' => PlanDeIntervencion::factory(),
            'entrevista_id' => Entrevista::factory()->seguimiento(),
            'profesional_id' => User::factory(),
            'fecha' => today()->toDateString(),
            'avances' => fake()->paragraph(),
            'objetivos_cumplidos' => null,
            'incidencias' => null,
            'nuevas_prestaciones' => null,
            'requiere_revision_plan' => false,
            'fecha_siguiente_seguimiento' => null,
        ];
    }

    /**
     * Seguimiento que requiere revisión del plan.
     */
    public function conRevisionPlan(): static
    {
        return $this->state(['requiere_revision_plan' => true]);
    }
}
