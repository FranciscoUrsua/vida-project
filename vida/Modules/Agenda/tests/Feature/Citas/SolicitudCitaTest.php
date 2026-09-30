<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Mensajes\Models\AlertaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo B: solicitudes (TF-CIT-04 a 08).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class SolicitudCitaTest extends TestCase
{
    use CitasTestSetup;
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioCitas();
    }

    /**
     * @return SolicitudCitaService
     */
    private function servicio(): SolicitudCitaService
    {
        return app(SolicitudCitaService::class);
    }

    /**
     * Solicitud ordinaria de María a su referencia, pedida por el TSR.
     *
     * @param array<string, mixed> $cambios
     * @return SolicitudCita
     */
    private function solicitud(array $cambios = []): SolicitudCita
    {
        return $this->servicio()->crear($cambios + [
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'ordinaria',
            'destino' => 'referencia',
            'motivo' => 'Revisar el plan tras la orden de protección',
            'observaciones_citacion' => 'Mejor por la tarde',
        ], $this->tsr);
    }

    /**
     * TF-CIT-04 — Intervención crea una solicitud: pendiente, con evento, aviso a
     * quienes dan citas y motivo cifrado.
     *
     * @return void
     */
    #[Test]
    public function intervencion_crea_una_solicitud(): void
    {
        $solicitud = $this->solicitud();

        $this->assertSame(EstadoSolicitudCita::Pendiente, $solicitud->estado);
        $this->assertSame($this->centro->id, $solicitud->centro_id);

        $evento = CitaEvento::sole();
        $this->assertSame(AccionCitaEvento::SolicitudCreada, $evento->accion);
        $this->assertSame($this->tsr->id, $evento->actor_id);

        $avisados = AlertaDestinatario::pluck('usuario_id')->all();
        $this->assertEqualsCanonicalizing([$this->consulta->id, $this->auxiliar->id], $avisados);

        $crudo = DB::table('solicitudes_cita')->where('id', $solicitud->id)->value('motivo');
        $this->assertStringNotContainsString('orden de protección', $crudo);
        $this->assertSame('Revisar el plan tras la orden de protección', $solicitud->fresh()->motivo);
    }

    /**
     * TF-CIT-05 — Sin fecha límite, se calcula con 7 días laborables para preferente.
     *
     * @return void
     */
    #[Test]
    public function la_fecha_limite_se_calcula_en_dias_laborables(): void
    {
        $solicitud = $this->solicitud(['urgencia' => 'preferente']);

        // Martes 6 + 7 laborables (salta el fin de semana del 10 y 11) = jueves 15
        $this->assertSame('2026-10-15', $solicitud->no_despues_de->toDateString());
    }

    /**
     * TF-CIT-06 — Destino profesional concreto exige profesional.
     *
     * @return void
     */
    #[Test]
    public function el_destino_profesional_concreto_exige_profesional(): void
    {
        try {
            $this->solicitud(['destino' => 'profesional_concreto']);
            $this->fail('Debía rechazarse');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('profesional_destino_id', $e->errors());
        }

        $this->assertSame(0, SolicitudCita::count());
        $this->assertSame(0, CitaEvento::count());
    }

    /**
     * TF-CIT-07 — Una solicitud solo la toma una persona. [negativo del bloqueo por estado]
     *
     * @return void
     */
    #[Test]
    public function una_solicitud_solo_la_toma_una_persona(): void
    {
        $solicitud = $this->solicitud();

        $this->servicio()->tomar($solicitud, $this->consulta);

        try {
            $this->servicio()->tomar($solicitud, $this->auxiliar);
            $this->fail('La segunda toma debía fallar');
        } catch (LogicException) {
        }

        $this->assertSame(EstadoSolicitudCita::EnGestion, $solicitud->fresh()->estado);
        $this->assertSame($this->consulta->id, $solicitud->fresh()->gestionada_por_id);
        $this->assertSame(1, CitaEvento::where('accion', AccionCitaEvento::SolicitudTomada)->count());
    }

    /**
     * TF-CIT-08 — Desistir exige motivo y es final; sin límite de intentos.
     *
     * @return void
     */
    #[Test]
    public function desistir_exige_motivo_y_es_final(): void
    {
        $solicitud = $this->servicio()->tomar($this->solicitud(), $this->consulta);

        try {
            $this->servicio()->desistir($solicitud, $this->consulta, '  ');
            $this->fail('Sin motivo debía rechazarse');
        } catch (InvalidArgumentException) {
        }

        $this->servicio()->desistir($solicitud, $this->consulta, 'No quiere cita');
        $this->assertSame(EstadoSolicitudCita::Desistida, $solicitud->fresh()->estado);
        $this->assertSame(1, CitaEvento::where('accion', AccionCitaEvento::SolicitudDesistida)->count());

        $this->expectException(LogicException::class);
        $this->servicio()->tomar($solicitud->fresh(), $this->consulta);
    }

    /**
     * Soltar devuelve a pendiente; solo quien la tiene puede soltarla.
     *
     * @return void
     */
    #[Test]
    public function soltar_la_devuelve_a_pendiente(): void
    {
        $solicitud = $this->servicio()->tomar($this->solicitud(), $this->consulta);

        try {
            $this->servicio()->liberar($solicitud, $this->auxiliar, 'No es mía');
            $this->fail('Solo quien la tiene puede soltarla');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
        }

        $this->servicio()->liberar($solicitud, $this->consulta, 'No contesta, probar mañana');
        $this->assertSame(EstadoSolicitudCita::Pendiente, $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->gestionada_por_id);
    }

    /**
     * Quien la pidió puede anularla; la referencia es obligatoria para el destino «referencia».
     *
     * @return void
     */
    #[Test]
    public function el_solicitante_anula_y_sin_referencia_no_hay_destino_referencia(): void
    {
        $solicitud = $this->solicitud();
        $this->servicio()->anular($solicitud, $this->tsr, 'Ya no es necesaria');
        $this->assertSame(EstadoSolicitudCita::Anulada, $solicitud->fresh()->estado);

        $this->expectException(ValidationException::class);
        $this->servicio()->crear([
            'ciudadano_id' => $this->juan->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoInformacion->id,
            'urgencia' => 'ordinaria',
            'destino' => 'referencia',
        ], $this->tsr);
    }
}
