<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * @extends Factory<PlanDeIntervencion>
 */
class PlanDeIntervencionFactory extends Factory
{
    protected $model = PlanDeIntervencion::class;

    public function definition(): array
    {
        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Factory Intervencion'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $historia = HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        return [
            'historia_id'                => $historia->id,
            'tipo'                       => TipoPlan::GeneralAsp,
            'servicio_especializado_id'  => null,
            'profesional_responsable_id' => User::factory(),
            'plan_asp_id'                => null,
            'estado'                     => EstadoPlan::Borrador,
            'fecha_inicio'               => today()->toDateString(),
            'fecha_firma'                => null,
            'fecha_cierre'               => null,
            'motivo_cierre'              => null,
            'objetivos'                  => fake()->paragraph(),
            'version'                    => 1,
        ];
    }

    /**
     * Plan en estado activo con version 1.
     */
    public function activo(): static
    {
        return $this->state(['estado' => EstadoPlan::Activo, 'version' => 1]);
    }

    /**
     * Plan en estado borrador.
     */
    public function borrador(): static
    {
        return $this->state(['estado' => EstadoPlan::Borrador]);
    }

    /**
     * Plan general ASP.
     */
    public function general(): static
    {
        return $this->state(['tipo' => TipoPlan::GeneralAsp]);
    }

    /**
     * Plan especializado.
     */
    public function especializado(): static
    {
        return $this->state(['tipo' => TipoPlan::Especializado]);
    }
}
