<?php

namespace Modules\Intervencion\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\Demo\DemoInvariantChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan as TipoPlanEnum;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — plan especializado con entrada directa (sin plan ASP previo).
 *
 * Regla de dominio: un plan especializado nace de una derivación desde un plan ASP
 * (plan_asp_id obligatorio), salvo que su tipo de plan admita entrada directa
 * (p. ej. el PIA del CIAM, puerta alternativa de entrada al sistema).
 *
 * TF-DEMO-CIAM-12 a TF-DEMO-CIAM-14.
 *
 * @see docs/modulo-intervencion.md §5.1, §5.2
 * @see docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md
 */
class PlanEntradaDirectaTest extends TestCase
{
    use RefreshDatabase;

    private HistoriaSocial $historia;

    private User $profesional;

    /**
     * Prepara una historia social abierta y un profesional.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $uo = UnidadOrganizativa::create(['nombre' => 'CIAM Test', 'tipo' => 'centro', 'activa' => true]);
        $this->profesional = User::factory()->create();

        $this->historia = HistoriaSocial::withoutGlobalScopes()->create([
            'ciudadano_id' => Ciudadano::factory()->create()->id,
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);
    }

    /**
     * TF-DEMO-CIAM-12: un plan especializado de un tipo con entrada directa se crea sin plan ASP.
     *
     * Dado un tipo de plan con admite_entrada_directa = true; cuando se crea un plan
     * especializado de ese tipo con plan_asp_id = null; entonces se persiste así.
     */
    #[Test]
    public function tf_demo_ciam_12_plan_especializado_con_entrada_directa_se_crea_sin_plan_asp(): void
    {
        $tipo = TipoPlan::factory()->entradaDirecta()->create();

        $plan = $this->crearPlanEspecializado($tipo->id);

        $this->assertDatabaseHas('planes_intervencion', [
            'id' => $plan->id,
            'tipo' => 'especializado',
            'tipo_plan_id' => $tipo->id,
            'plan_asp_id' => null,
        ]);
    }

    /**
     * TF-DEMO-CIAM-13: sin entrada directa, un plan especializado sin plan ASP es rechazado.
     *
     * Dado un tipo de plan con admite_entrada_directa = false (y también sin tipo de plan);
     * cuando se crea un plan especializado con plan_asp_id = null; entonces se lanza
     * DomainException y no se persiste. Control: con plan_asp_id sí se crea, y un plan
     * de entrada directa no puede cambiarse a un tipo sin entrada directa.
     */
    #[Test]
    public function tf_demo_ciam_13_plan_especializado_sin_entrada_directa_y_sin_plan_asp_es_rechazado(): void
    {
        $tipoNormal = TipoPlan::factory()->especializado()->create();

        foreach ([$tipoNormal->id, null] as $tipoPlanId) {
            try {
                $this->crearPlanEspecializado($tipoPlanId);
                $this->fail('Debe rechazarse un plan especializado sin plan ASP (tipo_plan_id='.var_export($tipoPlanId, true).').');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('derivación', $e->getMessage());
            }
        }

        $this->assertSame(0, DB::table('planes_intervencion')->where('tipo', 'especializado')->count());

        // Control positivo: nacido de una derivación (con plan ASP) sí se admite.
        $planAsp = PlanDeIntervencion::create([
            'historia_id' => $this->historia->id,
            'tipo' => TipoPlanEnum::GeneralAsp,
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => EstadoPlan::Borrador,
            'fecha_inicio' => today(),
            'version' => 1,
        ]);
        $derivado = $this->crearPlanEspecializado($tipoNormal->id, $planAsp->id);
        $this->assertSame($planAsp->id, $derivado->plan_asp_id);

        // Cambiar un plan de entrada directa a un tipo sin entrada directa también se rechaza.
        $directo = $this->crearPlanEspecializado(TipoPlan::factory()->entradaDirecta()->create()->id);
        $this->expectException(\DomainException::class);
        $directo->update(['tipo_plan_id' => $tipoNormal->id]);
    }

    /**
     * TF-DEMO-CIAM-14: el verificador de invariantes admite planes de entrada directa.
     *
     * Dado un plan especializado sin plan ASP cuyo tipo admite entrada directa; cuando se
     * ejecuta DemoInvariantChecker; entonces no reporta INV-02. En negativo: si el tipo deja
     * de admitir entrada directa (cambio directo en BD), el mismo plan sí se reporta.
     */
    #[Test]
    public function tf_demo_ciam_14_invariant_checker_no_reporta_planes_de_entrada_directa(): void
    {
        $tipo = TipoPlan::factory()->entradaDirecta()->create();
        $plan = $this->crearPlanEspecializado($tipo->id);

        $violaciones = (new DemoInvariantChecker)->check();
        $this->assertEmpty(array_filter($violaciones, fn (string $v) => str_starts_with($v, 'INV-02')));

        // También restringido a los planes indicados (modo aditivo).
        $this->assertSame([], (new DemoInvariantChecker)->check([$plan->id]));

        DB::table('tipos_plan')->where('id', $tipo->id)->update(['admite_entrada_directa' => false]);

        $violaciones = (new DemoInvariantChecker)->check([$plan->id]);
        $this->assertCount(1, array_filter($violaciones, fn (string $v) => str_starts_with($v, 'INV-02')));
    }

    /**
     * Crea un plan especializado activo en la historia de prueba.
     *
     * @param int|null $tipoPlanId Tipo de plan del catálogo
     * @param int|null $planAspId Plan ASP del que deriva (null = entrada directa)
     */
    private function crearPlanEspecializado(?int $tipoPlanId, ?int $planAspId = null): PlanDeIntervencion
    {
        return PlanDeIntervencion::create([
            'historia_id' => $this->historia->id,
            'tipo_plan_id' => $tipoPlanId,
            'tipo' => TipoPlanEnum::Especializado,
            'plan_asp_id' => $planAspId,
            'profesional_responsable_id' => $this->profesional->id,
            'estado' => EstadoPlan::Activo,
            'fecha_inicio' => today(),
            'fecha_firma' => today(),
            'version' => 1,
        ]);
    }
}
