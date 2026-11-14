<?php

namespace Modules\Intervencion\Database\Factories;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan as TipoPlanModelo;

/**
 * Factoría de planes de intervención para tests.
 *
 * Desde la fase 3 todo plan nuevo de una persona tiene tipo de plan: si el test no
 * lo indica, se usa el tipo principal (o uno especializado genérico si pide
 * `tipo = especializado`), creándolo si no existe. El `tipo` lo deriva el modelo.
 *
 * @extends Factory<PlanDeIntervencion>
 */
class PlanDeIntervencionFactory extends Factory
{
    protected $model = PlanDeIntervencion::class;

    /**
     * Atributos por defecto: plan principal en borrador de una historia nueva.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Factory Intervencion'],
            ['tipo' => 'centro', 'parent_id' => null, 'activa' => true]
        );

        $ciudadano = Ciudadano::factory()->create();
        $historia = HistoriaSocial::create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return [
            'historia_id' => $historia->id,
            'tipo' => null,
            'tipo_plan_id' => fn (array $atributos) => self::tipoPlanPara($atributos['tipo'] ?? null),
            'servicio_especializado_id' => null,
            'profesional_responsable_id' => User::factory(),
            'plan_asp_id' => null,
            'estado' => EstadoPlan::Borrador,
            'fecha_inicio' => today()->toDateString(),
            'fecha_firma' => null,
            'fecha_cierre' => null,
            'motivo_cierre' => null,
            'objetivos' => fake()->paragraph(),
            'version' => 1,
        ];
    }

    /**
     * Plan en estado activo con version 1.
     *
     * @return static
     */
    public function activo(): static
    {
        return $this->state(['estado' => EstadoPlan::Activo, 'version' => 1]);
    }

    /**
     * Plan en estado borrador.
     *
     * @return static
     */
    public function borrador(): static
    {
        return $this->state(['estado' => EstadoPlan::Borrador]);
    }

    /**
     * Plan general ASP.
     *
     * @return static
     */
    public function general(): static
    {
        return $this->state(['tipo' => TipoPlan::GeneralAsp]);
    }

    /**
     * Plan especializado.
     *
     * Al guardarse exige plan_asp_id o un tipo_plan_id con admite_entrada_directa
     * (ver PlanDeIntervencion::verificarOrigenPlanEspecializado()).
     *
     * @return static
     */
    public function especializado(): static
    {
        return $this->state(['tipo' => TipoPlan::Especializado]);
    }

    /**
     * Tipo de plan por defecto según el `tipo` pedido: el principal o un especializado genérico.
     *
     * @param TipoPlan|string|null $tipo
     *
     * @return int
     */
    public static function tipoPlanPara(TipoPlan|string|null $tipo): int
    {
        $tipo = is_string($tipo) ? TipoPlan::from($tipo) : $tipo;

        if ($tipo === TipoPlan::Especializado) {
            return TipoPlanModelo::firstOrCreate(
                ['slug' => 'esp_generico_test'],
                ['nombre' => 'Especializado (test)', 'ambito' => 'especializado', 'activo' => true],
            )->id;
        }

        // `piso` es el slug del PISO en los datos y en TipoCentroTestSetup: se reutiliza.
        return (TipoPlanModelo::principal()
            ?? TipoPlanModelo::updateOrCreate(['slug' => 'piso'], ['nombre' => 'PISO', 'ambito' => 'asp', 'es_principal' => true, 'activo' => true]))->id;
    }
}
