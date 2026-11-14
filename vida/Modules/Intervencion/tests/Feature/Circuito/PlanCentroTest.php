<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Services\PlanDeIntervencionService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo A — Centro del plan (TF-CIR-04, 05 y 06; fase 3, F3-2 y A2.2).
 *
 * Todo plan se abre en un centro que ofrece su tipo; el principal, solo en el
 * centro de la historia; un especializado sin entrada directa, solo por derivación.
 */
class PlanCentroTest extends TestCase
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

    /** TF-CIR-04 — Tipo de plan que el centro no ofrece, por servicio y por Livewire. [negativo] */
    #[Test]
    public function tf_cir_04_tipo_de_plan_que_el_centro_no_ofrece(): void
    {
        $this->planPia->update(['admite_entrada_directa' => true]);
        [, $historia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);

        $this->assertRechazado(fn () => app(PlanDeIntervencionService::class)->crear($this->datos($historia->id, $this->planPia->id), $this->cssNorte, $this->tsNorte));
        $this->assertRechazado(fn () => app(PlanDeIntervencionService::class)->crear($this->datos($historia->id, $this->planPiso->id), $this->ciamVallecas, $this->psicologaVallecas));

        $this->actingAs($this->tsNorte);
        Livewire::test(PlanPage::class, ['historia' => $historia->id])
            ->set('tipoPlanId', $this->planPia->id)
            ->assertHasErrors('tipoPlanId');

        $this->assertSame(0, PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $historia->id)->count());
    }

    /** TF-CIR-05 — El PISO solo en el centro de la historia (además de por el tipo). */
    #[Test]
    public function tf_cir_05_el_piso_solo_en_el_centro_de_la_historia(): void
    {
        [, $historia] = $this->personaEnCentro($this->cssNorte);

        // Aunque el CIAM ofreciera el PISO, no es el centro de la historia.
        $this->tipoCiam->tiposPlan()->attach($this->planPiso->id);

        try {
            app(PlanDeIntervencionService::class)->crear($this->datos($historia->id, $this->planPiso->id), $this->ciamVallecas, $this->psicologaVallecas);
            $this->fail('El PISO no puede abrirse fuera del centro de la historia');
        } catch (DomainException $e) {
            $this->assertStringContainsString('centro de la historia', $e->getMessage());
        }

        // Con referencia vigente, otro profesional del centro tampoco lo abre; la referencia, sí.
        [, $conReferencia] = $this->personaEnCentro($this->cssNorte, referencia: $this->tsNorte);
        try {
            app(PlanDeIntervencionService::class)->crear($this->datos($conReferencia->id, $this->planPiso->id), $this->cssNorte, $this->tsNorte2);
            $this->fail('Solo la referencia o la supervisión abren el plan principal');
        } catch (DomainException $e) {
            $this->assertStringContainsString('referencia', $e->getMessage());
        }
        $piso = app(PlanDeIntervencionService::class)->crear($this->datos($conReferencia->id, $this->planPiso->id), $this->cssNorte, $this->tsNorte);
        $this->assertTrue($piso->esPrincipal());
    }

    /** TF-CIR-06 — Entrada directa: con ella, PIA sin plan ASP en el CIAM; sin ella, rechazado. */
    #[Test]
    public function tf_cir_06_entrada_directa(): void
    {
        $servicio = app(PlanDeIntervencionService::class);

        $this->assertRechazado(fn () => $servicio->crear($this->datos($this->historiaAna->id, $this->planPia->id), $this->ciamVallecas, $this->psicologaVallecas));

        $this->planPia->update(['admite_entrada_directa' => true]);
        $pia = $servicio->crear($this->datos($this->historiaAna->id, $this->planPia->id), $this->ciamVallecas, $this->psicologaVallecas);

        $this->assertNull($pia->plan_asp_id);
        $this->assertSame($this->ciamVallecas->id, $pia->centro_id);
        $this->assertSame($this->psicologaVallecas->id, $pia->profesional_responsable_id);
    }

    /**
     * Datos mínimos de un plan en borrador.
     *
     * @param int $historiaId
     * @param int $tipoPlanId
     *
     * @return array<string, mixed>
     */
    private function datos(int $historiaId, int $tipoPlanId): array
    {
        return ['historia_id' => $historiaId, 'tipo_plan_id' => $tipoPlanId, 'estado' => EstadoPlan::Borrador, 'fecha_inicio' => '2026-11-02', 'version' => 1];
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
