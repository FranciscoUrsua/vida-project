<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Enums\TipoPlan as TipoPlanEnum;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\PlanDeIntervencionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Tipos de plan y plan principal (TF-CIR-01, 02, 39, 40 y 41; fase 3, F3-1).
 *
 * `tipo` se deriva del ámbito del tipo de plan y el plan principal (el PISO) lo
 * decide `tipos_plan.es_principal`, nunca `tipo`.
 */
class PlanTipoTest extends TestCase
{
    use CircuitoTestSetup;
    use RefreshDatabase;

    /**
     * Escenario del circuito.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCircuito();
    }

    /** TF-CIR-01 — El tipo sale del tipo de plan; uno incoherente se rechaza. [negativo] */
    #[Test]
    public function tf_cir_01_el_tipo_sale_del_tipo_de_plan(): void
    {
        $this->planPia->update(['admite_entrada_directa' => true]);
        [, $historiaCiam] = $this->personaEnCentro($this->ciamVallecas);

        $this->actingAs($this->psicologaVallecas);
        Livewire::test(PlanPage::class, ['historia' => $historiaCiam->id])->set('tipoPlanId', $this->planPia->id)->assertHasNoErrors();
        $pia = PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $historiaCiam->id)->sole();
        $this->assertSame(TipoPlanEnum::Especializado, $pia->tipo);
        $this->assertSame($this->ciamVallecas->id, $pia->centro_id);

        [, $historiaCss] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $this->actingAs($this->tsNorte);
        Livewire::test(PlanPage::class, ['historia' => $historiaCss->id])->set('tipoPlanId', $this->planPiso->id)->assertHasNoErrors();
        $piso = PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $historiaCss->id)->sole();
        $this->assertSame(TipoPlanEnum::GeneralAsp, $piso->tipo);

        $this->expectException(DomainException::class);
        PlanDeIntervencion::create([
            'historia_id' => $historiaCiam->id, 'tipo_plan_id' => $this->planPia->id, 'tipo' => TipoPlanEnum::GeneralAsp,
            'profesional_responsable_id' => $this->psicologaVallecas->id, 'estado' => EstadoPlan::Borrador,
            'fecha_inicio' => '2026-11-02', 'version' => 1,
        ]);
    }

    /** TF-CIR-02 — Ni el PIA ni otro plan de ASP se confunden con el PISO. [negativo] */
    #[Test]
    public function tf_cir_02_sin_piso_no_hay_plan_principal(): void
    {
        $this->planPia->update(['admite_entrada_directa' => true]);
        [, $historia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        $pia = $this->planActivo($historia, $this->planPia, $this->ciamVallecas, $this->psicologaVallecas);
        $psico = $this->planActivo($historia, $this->planPsicologico, $this->cssNorte, $this->psicologoNorte);

        // Los dos tienen tipo de ámbito: el PIA es especializado y el psicológico, de ASP.
        $this->assertSame(TipoPlanEnum::Especializado, $pia->tipo);
        $this->assertSame(TipoPlanEnum::GeneralAsp, $psico->tipo);
        $this->assertFalse($psico->esPrincipal());
        $this->assertFalse(PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $historia->id)->principal()->exists());
        $this->assertTrue(PlanDeIntervencion::withoutGlobalScopes()->whereKey($this->pisoAna->id)->principal()->exists());

        // MisCasos: cada responsable ve la historia con su plan nombrado por su tipo; nadie ve un PISO.
        foreach ([$this->psicologoNorte, $this->psicologaVallecas, $this->tsNorte] as $usuario) {
            $componente = Livewire::actingAs($usuario)->test(MisCasosPage::class);
            $this->assertContains($historia->id, collect($componente->get('casos')->items())->pluck('historia_id')->all());
            $this->assertEqualsCanonicalizing(
                ['PIA', 'Plan de intervención psicológica'],
                $componente->get('planesDelPage')->get($historia->id)->map(fn ($p) => $p->tipoPlan->nombre)->all(),
            );
        }
    }

    /** TF-CIR-39 — Varios tipos de plan en ASP: hay que elegir, y el principal no se ofrece si ya existe. */
    #[Test]
    public function tf_cir_39_varios_tipos_de_plan_en_asp(): void
    {
        $this->actingAs($this->psicologoNorte);
        $componente = Livewire::test(PlanPage::class, ['historia' => $this->historiaAna->id]);

        $this->assertSame(
            [$this->planPsicologico->id, $this->planSocioeducativo->id],
            array_keys($componente->get('tiposPlanes')),
        );
        $this->assertNull($componente->get('tipoPlanId'), 'Con dos tipos posibles no se preselecciona ninguno');

        $componente->call('crearNuevoPlan');
        $this->assertSame(1, PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)->count());

        $componente->set('tipoPlanId', $this->planPsicologico->id)->assertHasNoErrors();
        $plan = PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)
            ->where('tipo_plan_id', $this->planPsicologico->id)->sole();
        $this->assertSame(TipoPlanEnum::GeneralAsp, $plan->tipo);
        $this->assertFalse($plan->esPrincipal());
        $this->assertSame($this->psicologoNorte->id, $plan->profesional_responsable_id);
        $this->assertSame($this->cssNorte->id, $plan->centro_id);
    }

    /** TF-CIR-40 — Un plan vigente por tipo; tras cerrarlo, se puede abrir otro. [negativo] */
    #[Test]
    public function tf_cir_40_un_plan_vigente_por_tipo(): void
    {
        $servicio = app(PlanDeIntervencionService::class);
        $datos = ['historia_id' => $this->historiaAna->id, 'tipo_plan_id' => $this->planPsicologico->id, 'estado' => EstadoPlan::Borrador, 'fecha_inicio' => '2026-11-02', 'version' => 1];

        $primero = $servicio->crear($datos, $this->cssNorte, $this->psicologoNorte);

        $this->assertRechazado(fn () => $servicio->crear($datos, $this->cssNorte, $this->psicologoNorte));

        // La base de datos lo impide aunque se salte el servicio.
        try {
            // Savepoint: la violación no debe abortar la transacción del test.
            DB::transaction(fn () => PlanDeIntervencion::create($datos + ['profesional_responsable_id' => $this->psicologoNorte->id]));
            $this->fail('El índice único debía impedir un segundo plan vigente del mismo tipo');
        } catch (UniqueConstraintViolationException) {
            // esperado
        }

        $primero->update(['estado' => EstadoPlan::Cerrado, 'fecha_cierre' => '2026-11-02', 'motivo_cierre' => 'fin_intervencion']);
        $segundo = $servicio->crear($datos, $this->cssNorte, $this->psicologoNorte);
        $this->assertNotSame($primero->id, $segundo->id);

        // Segundo plan principal vigente
        $this->assertRechazado(fn () => $servicio->crear(
            ['historia_id' => $this->historiaAna->id, 'tipo_plan_id' => $this->planPiso->id, 'estado' => EstadoPlan::Borrador, 'fecha_inicio' => '2026-11-02', 'version' => 1],
            $this->cssNorte,
            $this->tsNorte,
        ));
    }

    /** TF-CIR-41 — Un solo tipo principal y siempre de ASP. */
    #[Test]
    public function tf_cir_41_un_solo_tipo_principal(): void
    {
        $this->assertRechazado(fn () => $this->planPsicologico->update(['es_principal' => true]));
        $this->assertRechazado(fn () => $this->planPia->update(['es_principal' => true]));

        $this->assertFalse($this->planPsicologico->fresh()->es_principal);
        $this->assertTrue($this->planPiso->fresh()->es_principal);
    }

    /**
     * Comprueba que la operación lanza DomainException.
     *
     * @param callable $operacion
     *
     * @return void
     */
    private function assertRechazado(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba DomainException');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
    }
}
