<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\RevisionPlan;
use Modules\Intervencion\Models\SeguimientoPlan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo C: Seguimiento.
 *
 * @see docs/modulo-intervencion.md §6, §10 Grupo C
 */
class SeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Test Seguimiento',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->profesional = User::factory()->create();
    }

    private function crearPlanActivo(): PlanDeIntervencion
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return PlanDeIntervencion::create([
            'historia_id' => $historia->id,
            'tipo' => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => EstadoPlan::Activo->value,
            'fecha_inicio' => today()->toDateString(),
            'version' => 1,
        ]);
    }

    private function crearEntrevistaSegimiento(PlanDeIntervencion $plan): Entrevista
    {
        return Entrevista::create([
            'historia_id' => $plan->historia_id,
            'profesional_id' => $this->profesional->id,
            'cita_id' => null,
            'plan_intervencion_id' => $plan->id,
            'fecha_hora' => now()->toDateTimeString(),
            'modalidad' => 'presencial',
            'tipo' => 'seguimiento',
            'estado' => 'realizada',
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-C01: Seguimiento sin requiere_revision_plan no altera el estado del plan.
     */
    #[Test]
    public function seguimiento_sin_revision_no_altera_el_plan(): void
    {
        $plan = $this->crearPlanActivo();
        $entrevista = $this->crearEntrevistaSegimiento($plan);

        $revisionesAntes = RevisionPlan::where('plan_id', $plan->id)->count();

        SeguimientoPlan::create([
            'plan_id' => $plan->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'avances' => 'Progreso satisfactorio',
            'requiere_revision_plan' => false,
        ]);

        $planRecargado = PlanDeIntervencion::find($plan->id);

        $this->assertEquals(1, $planRecargado->version, 'La versión del plan no debe cambiar');
        $this->assertEquals(EstadoPlan::Activo, $planRecargado->estado, 'El estado del plan debe seguir siendo activo');

        $revisionesDespues = RevisionPlan::where('plan_id', $plan->id)->count();
        $this->assertEquals($revisionesAntes, $revisionesDespues, 'No deben crearse nuevos registros en revisiones_plan');
    }

    /**
     * TF-INT-C02: nuevas_prestaciones se almacena y recupera como array.
     */
    #[Test]
    public function nuevas_prestaciones_se_recupera_como_array(): void
    {
        $plan = $this->crearPlanActivo();
        $entrevista = $this->crearEntrevistaSegimiento($plan);

        $seguimiento = SeguimientoPlan::create([
            'plan_id' => $plan->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'avances' => 'Con nuevas prestaciones',
            'requiere_revision_plan' => false,
            'nuevas_prestaciones' => [1, 4, 7],
        ]);

        $seguimientoRecargado = SeguimientoPlan::find($seguimiento->id);

        $this->assertIsArray($seguimientoRecargado->nuevas_prestaciones, 'nuevas_prestaciones debe ser un array PHP');
        $this->assertEquals([1, 4, 7], $seguimientoRecargado->nuevas_prestaciones);
    }

    /**
     * TF-INT-C03: solicitarCitaSiguiente existe y es callable sin excepción.
     */
    #[Test]
    public function solicitar_cita_siguiente_existe_y_no_lanza_excepcion(): void
    {
        $plan = $this->crearPlanActivo();
        $entrevista = $this->crearEntrevistaSegimiento($plan);

        $seguimiento = SeguimientoPlan::create([
            'plan_id' => $plan->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'avances' => 'Con fecha siguiente',
            'requiere_revision_plan' => false,
            'fecha_siguiente_seguimiento' => today()->addMonths(3)->toDateString(),
        ]);

        // Stub — la lógica real se implementará con la integración del módulo Agenda
        $this->assertNull($seguimiento->solicitarCitaSiguiente());
    }

    /**
     * TF-INT-C04: Seguimiento queda vinculado a su entrevista de origen.
     */
    #[Test]
    public function seguimiento_vinculado_a_su_entrevista_de_origen(): void
    {
        $plan = $this->crearPlanActivo();
        $entrevista = $this->crearEntrevistaSegimiento($plan);

        $seguimiento = SeguimientoPlan::create([
            'plan_id' => $plan->id,
            'entrevista_id' => $entrevista->id,
            'profesional_id' => $this->profesional->id,
            'fecha' => today()->toDateString(),
            'avances' => 'Avances del seguimiento',
            'requiere_revision_plan' => false,
        ]);

        $this->assertEquals(
            $entrevista->id,
            $seguimiento->entrevista->id,
            '$seguimiento->entrevista debe devolver la entrevista correcta'
        );

        $this->assertEquals(
            $seguimiento->id,
            $entrevista->seguimientoPlan->id,
            '$entrevista->seguimientoPlan debe devolver el seguimiento'
        );
    }
}
