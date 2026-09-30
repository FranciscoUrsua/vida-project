<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\EstadoSolicitudCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\CitaPrevia\AdaptadorCitaPrevia;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo D: dar, reprogramar y cancelar (TF-CIT-18 a 24).
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class CitacionTest extends TestCase
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
     * @return CitacionService
     */
    private function citacion(): CitacionService
    {
        return app(CitacionService::class);
    }

    /**
     * Datos de una cita directa para Juan con tsr2.
     *
     * @return array<string, mixed>
     */
    private function datosJuan(): array
    {
        return [
            'ciudadano_id' => $this->juan->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoInformacion->id,
            'urgencia' => 'ordinaria',
            'destino' => 'profesional_concreto',
            'profesional_destino_id' => $this->tsr2->id,
        ];
    }

    /**
     * TF-CIT-18 — Citar desde una solicitud en gestión.
     *
     * @return void
     */
    #[Test]
    public function citar_desde_una_solicitud(): void
    {
        $solicitud = app(SolicitudCitaService::class)->crear([
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'ordinaria',
            'destino' => 'referencia',
        ], $this->tsr);
        app(SolicitudCitaService::class)->tomar($solicitud, $this->consulta);
        $slot = $this->slotDe($this->tsr, '2026-10-07', '11:00');

        $cita = $this->citacion()->citar($solicitud, $slot, ModoAsignacionCita::Referencia, $this->consulta);

        $this->assertSame(EstadoCita::Confirmada, $cita->estado);
        $this->assertSame($solicitud->id, $cita->solicitud_cita_id);
        $this->assertSame($this->tipoSeguimiento->id, $cita->tipo_cita_id);
        $this->assertSame(ModoAsignacionCita::Referencia, $cita->modo_asignacion);
        $this->assertNotNull($cita->modalidad);
        $this->assertSame(EstadoSlot::Reservado, $slot->fresh()->estado);
        $this->assertSame(EstadoSolicitudCita::Citada, $solicitud->fresh()->estado);
        $this->assertNotNull($solicitud->fresh()->resuelta_en);
        $this->assertTrue(CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCreada)->exists());
    }

    /**
     * TF-CIT-19 — La cita directa es atómica: con un slot ya ocupado no queda ni la solicitud.
     *
     * @return void
     */
    #[Test]
    public function la_cita_directa_es_atomica(): void
    {
        $slot = $this->slotDe($this->tsr2, '2026-10-07', '11:00');
        $cita = $this->citacion()->citarDirecto($this->datosJuan(), $slot, ModoAsignacionCita::ProfesionalConcreto, $this->consulta);

        $this->assertSame(EstadoSolicitudCita::Citada, $cita->solicitud->estado);
        $this->assertSame(1, SolicitudCita::count());

        try {
            $this->citacion()->citarDirecto($this->datosJuan(), $slot, ModoAsignacionCita::ProfesionalConcreto, $this->auxiliar);
            $this->fail('El slot ya estaba reservado');
        } catch (LogicException) {
        }

        $this->assertSame(1, SolicitudCita::count());
        $this->assertSame(1, Cita::count());
    }

    /**
     * TF-CIT-20 — Reprogramar crea una cita nueva y libera el slot original.
     *
     * @return void
     */
    #[Test]
    public function reprogramar_crea_una_cita_nueva(): void
    {
        $original = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');
        $nuevo = $this->slotDe($this->tsr, '2026-10-08', '12:00');

        $nueva = $this->citacion()->reprogramar($original, $nuevo, PedidoPor::Ciudadano, 'Tiene médico', $this->consulta);

        $this->assertSame(EstadoCita::Reprogramada, $original->fresh()->estado);
        $this->assertSame(EstadoSlot::Disponible, $original->slot->fresh()->estado);
        $this->assertSame(EstadoCita::Confirmada, $nueva->estado);
        $this->assertSame($original->id, $nueva->cita_anterior_id);
        $this->assertSame($original->solicitud_cita_id, $nueva->solicitud_cita_id);

        foreach ([$original, $nueva] as $cita) {
            $evento = CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaReprogramada)->sole();
            $this->assertSame(PedidoPor::Ciudadano, $evento->pedido_por);
        }
    }

    /**
     * TF-CIT-21 — Una cita reprogramada no se reprograma otra vez; la cadena crece desde la vigente.
     *
     * @return void
     */
    #[Test]
    public function la_cadena_solo_crece_desde_la_cita_vigente(): void
    {
        $original = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');
        $segunda = $this->citacion()->reprogramar($original, $this->slotDe($this->tsr, '2026-10-08', '11:00'), PedidoPor::Ciudadano, 'Uno', $this->consulta);

        try {
            $this->citacion()->reprogramar($original->fresh(), $this->slotDe($this->tsr, '2026-10-09', '11:00'), PedidoPor::Ciudadano, 'Otra vez', $this->consulta);
            $this->fail('Una cita reprogramada no se reprograma');
        } catch (LogicException) {
        }

        $tercera = $this->citacion()->reprogramar($segunda, $this->slotDe($this->tsr, '2026-10-12', '11:00'), PedidoPor::Centro, 'Dos', $this->consulta);
        $cuarta = $this->citacion()->reprogramar($tercera, $this->slotDe($this->tsr, '2026-10-13', '11:00'), PedidoPor::Ciudadano, 'Tres', $this->consulta);

        $this->assertSame([$original->id, $segunda->id, $tercera->id, $cuarta->id], $cuarta->cadenaReprogramaciones()->pluck('id')->all());
    }

    /**
     * TF-CIT-22 — Cancelar exige motivo; con él cancela, deja evento y libera el slot futuro.
     *
     * @return void
     */
    #[Test]
    public function cancelar_exige_motivo_y_quien_lo_pide(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');

        try {
            $this->citacion()->cancelar($cita, PedidoPor::Centro, '', $this->consulta);
            $this->fail('Sin motivo debía rechazarse');
        } catch (InvalidArgumentException) {
        }

        $this->citacion()->cancelar($cita, PedidoPor::Centro, 'Cierre del centro por obras', $this->consulta);

        $this->assertSame(EstadoCita::Cancelada, $cita->fresh()->estado);
        $this->assertSame(PedidoPor::Centro, $cita->fresh()->pedido_por_cancelacion);
        $this->assertSame(EstadoSlot::Disponible, $cita->slot->fresh()->estado);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CitaCancelada)->count());
    }

    /**
     * TF-CIT-23 — Cancelar abriendo una solicitud nueva enlazada; sin la opción, ninguna.
     *
     * @return void
     */
    #[Test]
    public function cancelar_abriendo_una_solicitud_nueva(): void
    {
        $conSolicitud = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');
        $sinSolicitud = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-08');
        $antes = SolicitudCita::count();

        $nueva = $this->citacion()->cancelar($conSolicitud, PedidoPor::Ciudadano, 'No puede venir', $this->consulta, abrirSolicitud: true);

        $this->assertSame(EstadoSolicitudCita::Pendiente, $nueva->estado);
        $this->assertSame($conSolicitud->solicitud->tipo_cita_id, $nueva->tipo_cita_id);
        $this->assertSame($conSolicitud->solicitud->urgencia, $nueva->urgencia);
        $this->assertSame($conSolicitud->solicitud->destino, $nueva->destino);
        $this->assertSame($conSolicitud->solicitud_cita_id, $nueva->solicitud_anterior_id);

        $this->assertNull($this->citacion()->cancelar($sinSolicitud, PedidoPor::Ciudadano, 'No puede venir', $this->consulta));
        $this->assertSame($antes + 1, SolicitudCita::count());
    }

    /**
     * TF-CIT-24 — Los cambios sobre citas externas se notifican al sistema externo.
     *
     * @return void
     */
    #[Test]
    public function los_cambios_sobre_citas_externas_se_notifican(): void
    {
        $recibir = fn (string $referencia, string $fecha) => $this->citacion()->recibirExterna(
            app(AdaptadorCitaPrevia::class)->interpretar(['slot_id' => $this->slotDe($this->tsr2, $fecha, '11:00')->id, 'referencia_externa' => $referencia])
        )['cita'];

        $aReprogramar = $recibir('CP-1', '2026-10-07');
        $aCancelar = $recibir('CP-2', '2026-10-08');
        $this->assertSame(OrigenCita::ApiExterna, $aReprogramar->origen);

        $this->citacion()->reprogramar($aReprogramar, $this->slotDe($this->tsr2, '2026-10-09', '12:00'), PedidoPor::Ciudadano, 'Cambio', $this->consulta);
        $this->citacion()->cancelar($aCancelar, PedidoPor::Ciudadano, 'Anula', $this->consulta);

        $enviadas = app(AdaptadorCitaPrevia::class)->enviadas();
        $this->assertSame([['CP-1', 'reprogramada'], ['CP-2', 'cancelada']], array_map(fn ($n) => [$n['referencia_externa'], $n['accion']], $enviadas));
        $this->assertSame(2, CitaEvento::where('accion', AccionCitaEvento::NotificacionExternaEnviada)->count());
    }
}
