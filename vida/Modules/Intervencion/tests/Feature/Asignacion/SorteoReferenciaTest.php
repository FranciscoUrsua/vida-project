<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Services\Asignacion\SorteoReferenciaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo E: sorteo (TF-ASG-18 a TF-ASG-24).
 *
 * Los tests estadísticos usan semillas fijas y umbrales explícitos.
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class SorteoReferenciaTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /**
     * Monta el escenario común.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
    }

    /**
     * Abre $n historias nuevas en el centro y asigna su referencia; devuelve la
     * secuencia de profesionales asignados.
     *
     * @param int $n
     * @param Centro|null $centro
     * @return list<int>
     */
    private function asignarNuevas(int $n, ?Centro $centro = null): array
    {
        $servicio = app(AsignacionReferenciaService::class);
        $secuencia = [];

        for ($i = 0; $i < $n; $i++) {
            $historia = $this->historiaDe($this->crearCiudadano("Persona {$i}"), $centro);
            $secuencia[] = $servicio->asignarInicial($historia, $centro ?? $this->cssNorte, $this->ts1)->profesional_id;
        }

        return $secuencia;
    }

    /**
     * Recuento de asignaciones por profesional en una secuencia.
     *
     * @param list<int> $secuencia
     * @param User $profesional
     * @return int
     */
    private function cuenta(array $secuencia, User $profesional): int
    {
        return count(array_keys($secuencia, $profesional->id, true));
    }

    /**
     * Crea entradas que cuentan en el reparto, con el reparto de ts1-ts3 guardado.
     *
     * @param User $profesional
     * @param int $n
     * @param OrigenAsignacionReferencia $origen
     * @param string|null $fecha
     * @return void
     */
    private function entradasPrevias(User $profesional, int $n, OrigenAsignacionReferencia $origen = OrigenAsignacionReferencia::Sorteo, ?string $fecha = null): void
    {
        $pool = [
            ['usuario_id' => $this->ts1->id, 'peso' => 35.0],
            ['usuario_id' => $this->ts2->id, 'peso' => 35.0],
            ['usuario_id' => $this->ts3->id, 'peso' => 17.5],
        ];

        for ($i = 0; $i < $n; $i++) {
            AsignacionProfesional::create([
                'historia_id' => $this->historiaDe($this->crearCiudadano("Previa {$i}"))->id,
                'profesional_id' => $profesional->id,
                'centro_id' => $this->cssNorte->id,
                'origen' => $origen,
                'cuenta_en_reparto' => $origen->cuentaEnReparto(),
                'sorteo' => ['profesionales' => $pool, 'elegido' => $profesional->id],
                'fecha_inicio' => $fecha ?? today()->subMonth()->toDateString(),
            ]);
        }
    }

    /**
     * TF-ASG-18 — A medio plazo el reparto es proporcional a la jornada.
     *
     * @return void
     */
    #[Test]
    public function el_reparto_es_proporcional_a_la_jornada(): void
    {
        foreach ([11, 2026, 424242] as $semilla) {
            AsignacionProfesional::query()->forceDelete();
            $this->fijarSemilla($semilla);

            $secuencia = $this->asignarNuevas(250);

            $this->assertEqualsWithDelta(100, $this->cuenta($secuencia, $this->ts1), 1, "Semilla {$semilla}: ts1");
            $this->assertEqualsWithDelta(100, $this->cuenta($secuencia, $this->ts2), 1, "Semilla {$semilla}: ts2");
            $this->assertEqualsWithDelta(50, $this->cuenta($secuencia, $this->ts3), 1, "Semilla {$semilla}: ts3");
        }
    }

    /**
     * TF-ASG-19 — Quien va por debajo de lo que le corresponde es el único candidato.
     *
     * @return void
     */
    #[Test]
    public function corrige_el_desvio_a_favor_de_quien_va_por_debajo(): void
    {
        $this->entradasPrevias($this->ts1, 10);
        $this->entradasPrevias($this->ts2, 10);

        $resultado = app(SorteoReferenciaService::class)->sortear($this->cssNorte, today());

        $candidatos = array_column(array_filter($resultado->profesionales, fn ($p) => $p['candidato']), 'usuario_id');
        $this->assertSame([$this->ts3->id], $candidatos);
        $this->assertSame($this->ts3->id, $resultado->elegido->id);
    }

    /**
     * TF-ASG-20 — No es un turno predecible.
     *
     * @return void
     */
    #[Test]
    public function no_es_un_turno_predecible(): void
    {
        $secuencias = [];

        foreach ([7, 99] as $semilla) {
            AsignacionProfesional::query()->forceDelete();
            $this->fijarSemilla($semilla);
            $secuencias[$semilla] = $this->asignarNuevas(30);
        }

        $this->assertNotSame($secuencias[7], $secuencias[99]);

        foreach ($secuencias as $semilla => $secuencia) {
            $ciclica = true;
            for ($i = 0; $i + 3 < count($secuencia); $i++) {
                if ($secuencia[$i] !== $secuencia[$i + 3]) {
                    $ciclica = false;
                    break;
                }
            }
            $this->assertFalse($ciclica, "Semilla {$semilla}: la secuencia repite un ciclo fijo de 3");
        }
    }

    /**
     * TF-ASG-21 [negativo] — Los casos acumulados no influyen: se reparten entradas, no carga.
     *
     * @return void
     */
    #[Test]
    public function los_casos_acumulados_no_influyen_en_el_sorteo(): void
    {
        // 100 entradas antiguas, fuera de la ventana, y 100 vigentes que no cuentan
        $this->entradasPrevias($this->ts1, 100, OrigenAsignacionReferencia::Sorteo, today()->subYears(2)->toDateString());
        $this->entradasPrevias($this->ts1, 100, OrigenAsignacionReferencia::QuienAbre);
        $this->fijarSemilla(5);

        $secuencia = $this->asignarNuevas(50);

        $this->assertEqualsWithDelta(20, $this->cuenta($secuencia, $this->ts1), 1);
        $this->assertEqualsWithDelta(20, $this->cuenta($secuencia, $this->ts2), 1);
        $this->assertEqualsWithDelta(10, $this->cuenta($secuencia, $this->ts3), 1);
    }

    /**
     * TF-ASG-22 — Quien se incorpora no arrastra déficit de las entradas anteriores.
     *
     * @return void
     */
    #[Test]
    public function quien_se_incorpora_no_acapara_las_entradas_siguientes(): void
    {
        $this->fijarSemilla(3);
        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->asignarNuevas(100);
        Carbon::setTestNow('2026-10-01 10:00:00');

        $ts4 = $this->crearProfesionalEnCentro('ts4', $this->cssNorte, $this->cargoTs, 35, today()->toDateString());

        $primero = app(SorteoReferenciaService::class)->sortear($this->cssNorte, today());
        $ts4Auditado = collect($primero->profesionales)->firstWhere('usuario_id', $ts4->id);
        $this->assertSame(0.0, (float) $ts4Auditado['esperado']);

        // Parte de ts4 en 20 entradas: 35 / 122,5 ≈ 5,7
        $secuencia = $this->asignarNuevas(20);
        $this->assertLessThanOrEqual(7, $this->cuenta($secuencia, $ts4));
        $this->assertGreaterThanOrEqual(4, $this->cuenta($secuencia, $ts4));
    }

    /**
     * TF-ASG-23 — Cada sorteo guarda candidatos, pesos, esperado, recibido y elegido.
     *
     * @return void
     */
    #[Test]
    public function el_sorteo_es_auditable(): void
    {
        $this->entradasPrevias($this->ts1, 2);
        $this->fijarSemilla(1);

        $historia = $this->historiaDe($this->ana);
        $asignacion = app(AsignacionReferenciaService::class)->asignarInicial($historia, $this->cssNorte, $this->ts1);

        $sorteo = $asignacion->sorteo;
        $this->assertSame($asignacion->profesional_id, $sorteo['elegido']);
        $this->assertCount(3, $sorteo['profesionales']);
        foreach ($sorteo['profesionales'] as $p) {
            $this->assertArrayHasKey('usuario_id', $p);
            $this->assertArrayHasKey('peso', $p);
            $this->assertArrayHasKey('esperado', $p);
            $this->assertArrayHasKey('recibido', $p);
            $this->assertArrayHasKey('candidato', $p);
        }
        $ts1 = collect($sorteo['profesionales'])->firstWhere('usuario_id', $this->ts1->id);
        $this->assertSame(2, $ts1['recibido']);
        $this->assertEqualsWithDelta(0.8, $ts1['esperado'], 0.0001);
    }

    /**
     * TF-ASG-24 — Sin elegibles, la historia queda sin referencia y en la bandeja.
     *
     * @return void
     */
    #[Test]
    public function sin_elegibles_la_historia_queda_sin_referencia_en_la_bandeja(): void
    {
        $vacio = $this->crearCentro('CSS Vacío', $this->tipoCss);
        $historia = $this->historiaDe($this->ana, $vacio);

        $asignacion = app(AsignacionReferenciaService::class)->asignarInicial($historia, $vacio, $this->ts1);

        $this->assertNull($asignacion);
        $this->assertSame(0, AsignacionProfesional::where('historia_id', $historia->id)->count());
        $pendiente = AsignacionPendiente::abiertas()->where('historia_id', $historia->id)->first();
        $this->assertSame(TipoAsignacionPendiente::SinReferencia, $pendiente?->tipo);
        $this->assertSame(MotivoAsignacionPendiente::SinElegibles, $pendiente->motivo);
        $this->assertTrue(AsignacionPendiente::visiblesPara($vacio)->whereKey($pendiente->id)->exists());
    }
}
