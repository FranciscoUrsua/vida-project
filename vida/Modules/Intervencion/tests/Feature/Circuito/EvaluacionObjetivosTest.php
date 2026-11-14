<?php

namespace Modules\Intervencion\Tests\Feature\Circuito;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\PlanIndicadorValoracion;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Intervencion\Services\EvaluacionObjetivosService;
use Modules\Intervencion\Tests\Concerns\CircuitoTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Evaluación de objetivos en el seguimiento (TF-CIR-28 a 33; fase 3, F3-7).
 *
 * El historial de valoraciones es inmutable (principio 4.2): corregir es valorar de nuevo.
 */
class EvaluacionObjetivosTest extends TestCase
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

    /** TF-CIR-28 — Valorar en el seguimiento: historial con seguimiento y valoración actual al día. */
    #[Test]
    public function tf_cir_28_valorar_en_el_seguimiento(): void
    {
        [$ind1, $ind2] = [$this->objetivoAna1->indicador, $this->objetivoAna2->indicador];

        $this->registrarSeguimientoDesdeLaFicha([$ind1->id => 'en_proceso', $ind2->id => 'si']);

        $seguimiento = SeguimientoPlan::where('plan_id', $this->pisoAna->id)->sole();
        $this->assertSame(2, PlanIndicadorValoracion::where('seguimiento_id', $seguimiento->id)->count());
        $this->assertSame(['en_proceso', $seguimiento->id], [$ind1->fresh()->valoracion_actual, $ind1->fresh()->seguimiento_id]);
        $this->assertSame('si', $ind2->fresh()->valoracion_actual);
        $this->assertSame('2026-11-02', $ind2->fresh()->fecha_valoracion->toDateString());
    }

    /** TF-CIR-29 — Propuesta de estado: se propone y el profesional la cambia; `no_conseguido` no propone. */
    #[Test]
    public function tf_cir_29_propuesta_de_estado(): void
    {
        $ind1 = $this->objetivoAna1->indicador;

        $this->actingAs($this->tsNorte);
        $componente = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->set('valoracionesSeguimiento.'.$ind1->id, 'conseguido');
        $this->assertSame('conseguido', $componente->get('estadosObjetivos')[$this->objetivoAna1->id]);

        $componente->set('estadosObjetivos.'.$this->objetivoAna1->id, 'en_proceso');
        $this->guardarSeguimiento($componente);
        $this->assertSame('en_proceso', $this->objetivoAna1->fresh()->estado);

        $servicio = app(EvaluacionObjetivosService::class);
        $this->assertNull($servicio->propuestaDeEstado('no_conseguido'));
        $this->assertSame('en_proceso', $servicio->propuestaDeEstado('favorable'));
        $this->assertNull($servicio->propuestaDeEstado('desfavorable'));
    }

    /** TF-CIR-30 — Historial, no sobrescritura; la ficha del plan muestra la evolución en orden. [negativo] */
    #[Test]
    public function tf_cir_30_historial_no_sobrescritura(): void
    {
        $ind1 = $this->objetivoAna1->indicador;

        $this->registrarSeguimientoDesdeLaFicha([$ind1->id => 'no_conseguido']);
        $this->registrarSeguimientoDesdeLaFicha([$ind1->id => 'en_proceso']);

        $this->assertSame(['no_conseguido', 'en_proceso'], $ind1->valoraciones()->pluck('valoracion')->all());
        $this->assertSame('en_proceso', $ind1->fresh()->valoracion_actual);

        $this->actingAs($this->tsNorte);
        Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])->assertSeeInOrder(['Evolución:', 'No conseguido', 'En proceso']);

        // Ni Eloquent ni SQL directo cambian el historial.
        try {
            DB::transaction(fn () => DB::statement('update plan_indicador_valoraciones set valoracion = ? where plan_objetivo_indicador_id = ?', ['conseguido', $ind1->id]));
            $this->fail('El trigger debía impedir la modificación');
        } catch (QueryException $e) {
            $this->assertStringContainsString('solo inserción', $e->getMessage());
        }
    }

    /** TF-CIR-31 — Valorar es opcional: el seguimiento se guarda y los indicadores no cambian. */
    #[Test]
    public function tf_cir_31_valorar_es_opcional(): void
    {
        $this->registrarSeguimientoDesdeLaFicha([]);

        $this->assertSame(1, SeguimientoPlan::where('plan_id', $this->pisoAna->id)->count());
        $this->assertSame(0, PlanIndicadorValoracion::count());
        $this->assertNull($this->objetivoAna1->indicador->fresh()->valoracion_actual);
        $this->assertSame('pendiente', $this->objetivoAna1->fresh()->estado);
    }

    /** TF-CIR-32 — Transacción: si falla una valoración, ni seguimiento ni cambios en objetivos. */
    #[Test]
    public function tf_cir_32_transaccion(): void
    {
        [$ind1, $ind2] = [$this->objetivoAna1->indicador, $this->objetivoAna2->indicador];

        $this->actingAs($this->tsNorte);
        $componente = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna])
            ->set('valoracionesSeguimiento.'.$ind1->id, 'conseguido')
            ->set('valoracionesSeguimiento.'.$ind2->id, 'favorable'); // no vale para un indicador sí/no
        $this->guardarSeguimiento($componente)->assertHasErrors('evaluacion');

        $this->assertSame(0, SeguimientoPlan::count());
        $this->assertSame(0, PlanIndicadorValoracion::count());
        $this->assertNull($ind1->fresh()->valoracion_actual);
        $this->assertSame('pendiente', $this->objetivoAna1->fresh()->estado);
    }

    /** TF-CIR-33 — Valorar al editar el plan también deja historial, sin seguimiento. */
    #[Test]
    public function tf_cir_33_valorar_al_editar_el_plan(): void
    {
        $ind1 = $this->objetivoAna1->indicador;

        $this->actingAs($this->tsNorte);
        Livewire::test(PlanPage::class, ['plan' => $this->pisoAna])->call('guardarValoracionIndicador', $ind1->id, 'en_proceso');

        $valoracion = PlanIndicadorValoracion::where('plan_objetivo_indicador_id', $ind1->id)->sole();
        $this->assertNull($valoracion->seguimiento_id);
        $this->assertSame([$this->tsNorte->id, 'en_proceso'], [$valoracion->profesional_id, $valoracion->valoracion]);
    }

    /**
     * Registra desde la ficha una entrevista con seguimiento y las valoraciones dadas.
     *
     * @param array<int, string> $valoraciones
     *
     * @return void
     */
    private function registrarSeguimientoDesdeLaFicha(array $valoraciones): void
    {
        $this->actingAs($this->tsNorte);
        $componente = Livewire::test(CiudadanoPage::class, ['historia' => $this->historiaAna]);

        foreach ($valoraciones as $indicadorId => $valor) {
            $componente->set('valoracionesSeguimiento.'.$indicadorId, $valor);
        }

        $this->guardarSeguimiento($componente)->assertHasNoErrors();
    }

    /**
     * Guarda la entrevista programando el siguiente seguimiento.
     *
     * @param Testable $componente
     *
     * @return Testable
     */
    private function guardarSeguimiento($componente)
    {
        return $componente
            ->set('herramientaActiva', 'entrevista')
            ->set('formEntrevista.programar_seguimiento', true)
            ->set('formEntrevista.fecha_siguiente_seguimiento', '2026-12-01')
            ->call('guardarEntrevista');
    }
}
