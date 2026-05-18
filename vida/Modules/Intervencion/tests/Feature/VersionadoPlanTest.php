<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan;
use Modules\Intervencion\Models\Entrevista;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\RevisionPlan;
use Modules\Intervencion\Models\SeguimientoPlan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo B: Versionado e integridad del plan.
 *
 * @see docs/modulo-intervencion.md §5, §10 Grupo B
 */
class VersionadoPlanTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;
    private User $profesional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Test Versionado',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->profesional = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearPlanActivo(): PlanDeIntervencion
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        return PlanDeIntervencion::create([
            'historia_id'                => $historia->id,
            'tipo'                       => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $this->profesional->id,
            'estado'                     => EstadoPlan::Activo->value,
            'fecha_inicio'               => today()->toDateString(),
            'version'                    => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * TF-INT-B01: crearNuevaVersion incrementa version y archiva la anterior.
     */
    #[Test]
    public function crear_nueva_version_incrementa_version_y_archiva_anterior(): void
    {
        $plan = $this->crearPlanActivo();

        $nuevoPlan = $plan->crearNuevaVersion('Cambio de circunstancias', $this->profesional->id);

        $this->assertEquals(2, $nuevoPlan->version, 'La nueva instancia debe tener version 2');
        $this->assertEquals(EstadoPlan::Borrador, $nuevoPlan->estado, 'La nueva instancia debe estar en borrador');

        // Recargar el plan original desde BD para verificar su estado actualizado
        $planOriginalEnBd = PlanDeIntervencion::find($plan->id);
        $this->assertEquals(
            EstadoPlan::EnRevision,
            $planOriginalEnBd->estado,
            'El plan original debe quedar en estado en_revision'
        );
    }

    /**
     * TF-INT-B02: crearNuevaVersion crea registro en revisiones_plan.
     */
    #[Test]
    public function crear_nueva_version_crea_registro_en_revisiones_plan(): void
    {
        $plan = $this->crearPlanActivo();

        $plan->crearNuevaVersion('Motivo de prueba', $this->profesional->id);

        $revision = RevisionPlan::where('plan_id', $plan->id)->first();

        $this->assertNotNull($revision, 'Debe existir un registro en revisiones_plan');
        $this->assertEquals(1, $revision->version_anterior);
        $this->assertEquals(2, $revision->version_nueva);
        $this->assertEquals($this->profesional->id, $revision->profesional_id);
        $this->assertEquals('Motivo de prueba', $revision->motivo_revision);
    }

    /**
     * TF-INT-B03: crearNuevaVersion registra el seguimiento de origen cuando se proporciona.
     */
    #[Test]
    public function crear_nueva_version_registra_seguimiento_de_origen(): void
    {
        $plan = $this->crearPlanActivo();

        $entrevista = Entrevista::factory()->seguimiento()->create([
            'historia_id'    => $plan->historia_id,
            'profesional_id' => $this->profesional->id,
        ]);

        $seguimiento = SeguimientoPlan::create([
            'plan_id'               => $plan->id,
            'entrevista_id'         => $entrevista->id,
            'profesional_id'        => $this->profesional->id,
            'fecha'                 => today()->toDateString(),
            'avances'               => 'Progreso observado',
            'requiere_revision_plan' => true,
        ]);

        $plan->crearNuevaVersion('Revisión por seguimiento', $this->profesional->id, $seguimiento->id);

        $revision = RevisionPlan::where('plan_id', $plan->id)->first();

        $this->assertEquals($seguimiento->id, $revision->seguimiento_id, 'El seguimiento de origen debe quedar registrado');
    }

    /**
     * TF-INT-B04: estaFirmado devuelve false sin firmas.
     */
    #[Test]
    public function esta_firmado_devuelve_false_sin_firmas(): void
    {
        $plan = $this->crearPlanActivo();

        $this->assertFalse($plan->estaFirmado(), 'Un plan sin registros en firmas_plan no está firmado');
    }

    /**
     * TF-INT-B05: estaFirmado devuelve false con solo firma del ciudadano.
     */
    #[Test]
    public function esta_firmado_devuelve_false_con_solo_una_firma(): void
    {
        $plan = $this->crearPlanActivo();

        FirmaPlan::create([
            'plan_id'           => $plan->id,
            'version'           => 1,
            'firma_ciudadano'   => 'firma_blob_ciudadano',
            'firma_profesional' => null,
        ]);

        $this->assertFalse($plan->estaFirmado(), 'Con solo la firma del ciudadano el plan no está firmado');
    }

    /**
     * TF-INT-B06: estaFirmado devuelve true con ambas firmas en la versión correcta.
     */
    #[Test]
    public function esta_firmado_devuelve_true_con_ambas_firmas(): void
    {
        $plan = $this->crearPlanActivo();

        FirmaPlan::create([
            'plan_id'           => $plan->id,
            'version'           => 1,
            'firma_ciudadano'   => 'firma_ciudadano_v1',
            'firma_profesional' => 'firma_profesional_v1',
        ]);

        $this->assertTrue($plan->estaFirmado(), 'Con ambas firmas para la versión correcta, el plan está firmado');
    }

    /**
     * TF-INT-B07: estaFirmado evalúa la versión actual, no versiones anteriores.
     */
    #[Test]
    public function esta_firmado_evalua_version_actual_no_anteriores(): void
    {
        $plan = $this->crearPlanActivo();

        // Existe firma para la versión 1 con ambas partes
        FirmaPlan::create([
            'plan_id'           => $plan->id,
            'version'           => 1,
            'firma_ciudadano'   => 'firma_ciudadano_v1',
            'firma_profesional' => 'firma_profesional_v1',
        ]);

        // Se genera una nueva versión (version=2, sin firmas)
        $nuevoPlan = $plan->crearNuevaVersion('Cambio de objetivos', $this->profesional->id);

        // La nueva versión no tiene FirmaPlan para version=2
        $this->assertFalse(
            $nuevoPlan->estaFirmado(),
            'La firma de la versión anterior no debe validar la nueva versión del plan'
        );
    }

    /**
     * TF-INT-B08: Un plan borrador no puede activarse sin firma.
     */
    #[Test]
    public function plan_borrador_no_puede_activarse_sin_firma(): void
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $plan = PlanDeIntervencion::create([
            'historia_id'                => $historia->id,
            'tipo'                       => TipoPlan::GeneralAsp,
            'profesional_responsable_id' => $this->profesional->id,
            'estado'                     => EstadoPlan::Borrador,
            'fecha_inicio'               => today()->toDateString(),
            'version'                    => 1,
        ]);

        $this->expectException(\DomainException::class);

        $plan->update(['estado' => EstadoPlan::Activo]);
    }

    /**
     * TF-INT-B09: Planes especializados mantienen referencia al plan ASP de origen.
     */
    #[Test]
    public function plan_especializado_mantiene_referencia_al_plan_asp(): void
    {
        $planAsp = $this->crearPlanActivo();

        $historia = HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $planEspecializado = PlanDeIntervencion::create([
            'historia_id'                => $historia->id,
            'tipo'                       => TipoPlan::Especializado,
            'profesional_responsable_id' => $this->profesional->id,
            'plan_asp_id'                => $planAsp->id,
            'estado'                     => EstadoPlan::Borrador,
            'fecha_inicio'               => today()->toDateString(),
            'version'                    => 1,
        ]);

        // Verificar relación desde el plan especializado hacia el ASP
        $this->assertEquals(
            $planAsp->id,
            $planEspecializado->planAsp->id,
            'El plan especializado debe referenciar al plan ASP de origen'
        );

        // Verificar relación en sentido inverso
        $this->assertTrue(
            $planAsp->planesEspecializados->contains($planEspecializado),
            'El plan ASP debe incluir el plan especializado en su colección'
        );
    }
}
