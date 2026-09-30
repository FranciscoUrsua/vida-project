<?php

namespace Modules\Intervencion\Tests\Feature\Asignacion;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Modules\Centro\Tests\Concerns\AsignacionTestSetup;
use Modules\Intervencion\Enums\EstadoRepartoCasos;
use Modules\Intervencion\Enums\OrigenAsignacionReferencia;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Intervencion\Models\RepartoCasos;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Services\Asignacion\RepartoCasosService;
use Modules\Mensajes\Models\Alerta;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo G: reparto por salida de un profesional (TF-ASG-31 y TF-ASG-32).
 *
 * @see docs/instrucciones-cli/2026-09-asignacion-tests.md
 */
class RepartoCasosTest extends TestCase
{
    use AsignacionTestSetup;
    use RefreshDatabase;

    /** @var list<int> Historias de ts1 con actividad (incluye las de la unidad García). */
    private array $activas = [];

    /** @var list<int> Historias dormidas de ts1. */
    private array $dormidas = [];

    /** @var list<int> Historias de Pedro y Lucía (unidad García). */
    private array $garcia = [];

    /**
     * Escenario: ts1 con 20 casos con actividad (entre ellos la unidad García
     * completa) y 40 dormidos en CSS Norte.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->montarEscenarioAsignacion();
        $this->fijarSemilla(7);

        foreach ([$this->pedro, $this->lucia] as $miembro) {
            $this->garcia[] = $this->casoDeTs1($this->historiaDe($miembro), true);
        }

        for ($i = 0; $i < 18; $i++) {
            $this->casoDeTs1($this->historiaDe($this->crearCiudadano("Activa {$i}")), true);
        }

        for ($i = 0; $i < 40; $i++) {
            $this->casoDeTs1($this->historiaDe($this->crearCiudadano("Dormida {$i}")), false);
        }
    }

    /**
     * Deja a ts1 como referencia de la historia y, si procede, le da actividad
     * con un apunte reciente.
     *
     * @param HistoriaSocial $historia
     * @param bool $conActividad
     * @return int Id de la historia.
     */
    private function casoDeTs1(HistoriaSocial $historia, bool $conActividad): int
    {
        AsignacionProfesional::create([
            'historia_id' => $historia->id,
            'profesional_id' => $this->ts1->id,
            'centro_id' => $this->cssNorte->id,
            'origen' => OrigenAsignacionReferencia::Sorteo,
            'cuenta_en_reparto' => true,
            'fecha_inicio' => '2026-01-15',
        ]);

        if ($conActividad) {
            Apunte::factory()->create([
                'historia_id' => $historia->id,
                'autor_id' => $this->ts1->id,
                'fecha' => now()->subMonth()->toDateString(),
            ]);
            $this->activas[] = $historia->id;
        } else {
            $this->dormidas[] = $historia->id;
        }

        return $historia->id;
    }

    /**
     * @return RepartoCasosService
     */
    private function servicio(): RepartoCasosService
    {
        return app(RepartoCasosService::class);
    }

    /**
     * @return RepartoCasos
     */
    private function proponer(): RepartoCasos
    {
        return $this->servicio()->proponer($this->cssNorte, $this->ts1, 'Traslado a otro centro', $this->supervisor);
    }

    /**
     * Vigentes de ts1 en el centro.
     *
     * @return int
     */
    private function vigentesDeTs1(): int
    {
        return AsignacionProfesional::vigente()->where('profesional_id', $this->ts1->id)->count();
    }

    /**
     * TF-ASG-31 — La propuesta reparte cada bloque en proporción a la jornada,
     * deja entera la unidad de convivencia y no cambia ninguna asignación.
     *
     * @return void
     */
    #[Test]
    public function la_propuesta_reparte_por_bloques_en_proporcion_sin_cambiar_nada(): void
    {
        $reparto = $this->proponer();

        $this->assertSame(EstadoRepartoCasos::Propuesto, $reparto->estado);
        $lineas = $reparto->lineas()->get();
        $this->assertCount(60, $lineas);
        $this->assertEqualsCanonicalizing($this->activas, $lineas->where('con_actividad', true)->pluck('historia_id')->all());
        $this->assertEqualsCanonicalizing($this->dormidas, $lineas->where('con_actividad', false)->pluck('historia_id')->all());

        foreach ([true => 20, false => 40] as $conActividad => $total) {
            $bloque = $lineas->where('con_actividad', (bool) $conActividad);
            $aTs2 = $bloque->where('profesional_destino_id', $this->ts2->id)->count();
            $aTs3 = $bloque->where('profesional_destino_id', $this->ts3->id)->count();

            $this->assertSame($total, $aTs2 + $aTs3, 'solo ts2 y ts3 reciben: ni ts1 ni el educador');
            $this->assertEqualsWithDelta($total * 2 / 3, $aTs2, 1, 'ts2 (35 h) recibe el doble que ts3 (17,5 h)');
        }

        $destinosGarcia = $lineas->whereIn('historia_id', $this->garcia)->pluck('profesional_destino_id')->unique();
        $this->assertCount(1, $destinosGarcia, 'la unidad de convivencia va entera al mismo destino');
        $this->assertSame($this->ucGarcia->id, $lineas->firstWhere('historia_id', $this->garcia[0])->unidad_convivencia_id);

        $this->assertSame(60, $this->vigentesDeTs1(), 'nada cambia hasta confirmar');
        $this->assertSame(0, AsignacionProfesional::where('origen', OrigenAsignacionReferencia::Reparto)->count());
    }

    /**
     * TF-ASG-31 — Solo la supervisión del centro puede proponer, y con motivo.
     *
     * @return void
     */
    #[Test]
    public function solo_la_supervision_del_centro_propone_y_con_motivo(): void
    {
        foreach ([$this->ts2, $this->supervisorSur] as $ajeno) {
            try {
                $this->servicio()->proponer($this->cssNorte, $this->ts1, 'Traslado', $ajeno);
                $this->fail("{$ajeno->email} no debería poder repartir los casos de CSS Norte");
            } catch (AuthorizationException) {
                // Esperado
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->servicio()->proponer($this->cssNorte, $this->ts1, '   ', $this->supervisor);
    }

    /**
     * TF-ASG-32 — Al confirmar se cierran las 60 asignaciones de ts1 y se crean
     * 60 de reparto que no cuentan en el sorteo; la línea modificada manda y los
     * destinos reciben aviso.
     *
     * @return void
     */
    #[Test]
    public function confirmar_aplica_el_reparto_con_la_linea_modificada_y_avisa(): void
    {
        $reparto = $this->proponer();
        $linea = $reparto->lineas()->where('profesional_destino_id', $this->ts2->id)->where('con_actividad', false)->firstOrFail();
        $this->servicio()->modificarLinea($linea, $this->ts3, $this->supervisor);

        $this->servicio()->confirmar($reparto, $this->supervisor);

        $this->assertSame(0, $this->vigentesDeTs1());
        $this->assertSame(60, AsignacionProfesional::where('profesional_id', $this->ts1->id)->whereDate('fecha_fin', today())->count());

        $nuevas = AsignacionProfesional::vigente()->where('reparto_id', $reparto->id)->get();
        $this->assertCount(60, $nuevas);
        $this->assertTrue($nuevas->every(fn (AsignacionProfesional $a) => $a->origen === OrigenAsignacionReferencia::Reparto
            && $a->cuenta_en_reparto === false
            && $a->centro_id === $this->cssNorte->id
            && $a->asignado_por_id === $this->supervisor->id));

        $this->assertSame($this->ts3->id, $nuevas->firstWhere('historia_id', $linea->historia_id)->profesional_id, 'la línea modificada respeta el cambio');
        $this->assertTrue($linea->fresh()->modificada_por_supervisor);

        $reparto->refresh();
        $this->assertSame(EstadoRepartoCasos::Confirmado, $reparto->estado);
        $this->assertNotNull($reparto->confirmado_en);

        foreach ([$this->ts2, $this->ts3] as $destino) {
            $recibidos = $nuevas->where('profesional_id', $destino->id)->count();
            $aviso = Alerta::where('origen_type', RepartoCasos::class)->where('origen_id', $reparto->id)
                ->where('destinatario_usuario_id', $destino->id)->sole();
            $this->assertStringContainsString("{$recibidos} casos", $aviso->titulo);
            $this->assertCount(1, $aviso->destinatarios);
        }
        $this->assertSame(2, Alerta::where('origen_type', RepartoCasos::class)->count(), 'ni ts1 ni el educador reciben aviso');
    }

    /**
     * TF-ASG-32 — Descartar una propuesta no cambia nada, y después ya no se
     * puede confirmar.
     *
     * @return void
     */
    #[Test]
    public function descartar_no_cambia_nada(): void
    {
        $reparto = $this->proponer();

        $this->servicio()->descartar($reparto, $this->supervisor);

        $this->assertSame(EstadoRepartoCasos::Descartado, $reparto->fresh()->estado);
        $this->assertSame(60, $this->vigentesDeTs1());
        $this->assertSame(0, Alerta::where('origen_type', RepartoCasos::class)->count());

        $this->expectException(LogicException::class);
        $this->servicio()->confirmar($reparto->fresh(), $this->supervisor);
    }

    /**
     * TF-ASG-32 [negativo] — Si un caso cambia de referencia tras la propuesta,
     * la confirmación no aplica nada: ni ese caso ni los demás.
     *
     * @return void
     */
    #[Test]
    public function una_propuesta_desfasada_no_se_aplica_a_medias(): void
    {
        $reparto = $this->proponer();
        $historia = HistoriaSocial::withoutGlobalScopes()->findOrFail($this->dormidas[0]);
        app(AsignacionReferenciaService::class)->cambiarManual($historia, $this->ts2, 'Parentesco', $this->supervisor);

        try {
            $this->servicio()->confirmar($reparto, $this->supervisor);
            $this->fail('La confirmación de una propuesta desfasada debería fallar');
        } catch (LogicException) {
            // Esperado
        }

        $this->assertSame(59, $this->vigentesDeTs1());
        $this->assertSame(0, AsignacionProfesional::where('origen', OrigenAsignacionReferencia::Reparto)->count());
        $this->assertSame(EstadoRepartoCasos::Propuesto, $reparto->fresh()->estado);
    }

    /**
     * TF-ASG-31 [negativo] — Solo puede haber un reparto propuesto por
     * profesional, y el destino modificado debe estar en el reparto del centro.
     *
     * @return void
     */
    #[Test]
    public function no_admite_dos_propuestas_ni_destinos_fuera_del_reparto(): void
    {
        $reparto = $this->proponer();

        try {
            $this->proponer();
            $this->fail('No debería admitir un segundo reparto propuesto');
        } catch (LogicException) {
            // Esperado
        }

        $linea = $reparto->lineas()->firstOrFail();

        foreach ([$this->ts1, $this->educador] as $invalido) {
            try {
                $this->servicio()->modificarLinea($linea, $invalido, $this->supervisor);
                $this->fail("{$invalido->email} no puede ser destino");
            } catch (LogicException) {
                // Esperado
            }
        }

        $this->assertFalse($linea->fresh()->modificada_por_supervisor);
    }
}
