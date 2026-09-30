<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Mensajes\Models\MensajeHilo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo E: permisos (TF-CIT-25 a 28), en los servicios.
 * Las pantallas lo comprueban también en los tests de interfaz.
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class PermisosCitaTest extends TestCase
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
     * Datos de cita directa para Juan.
     *
     * @param \App\Models\User $profesional
     * @return array<string, mixed>
     */
    private function datosJuan($profesional): array
    {
        return [
            'ciudadano_id' => $this->juan->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoInformacion->id,
            'urgencia' => 'ordinaria',
            'destino' => 'profesional_concreto',
            'profesional_destino_id' => $profesional->id,
        ];
    }

    /**
     * TF-CIT-25 — Intervención no da citas, ni directas ni desde una solicitud. [negativo]
     *
     * @return void
     */
    #[Test]
    public function intervencion_no_da_citas(): void
    {
        $slot = $this->slotDe($this->tsr2, '2026-10-07', '11:00');

        try {
            $this->citacion()->citarDirecto($this->datosJuan($this->tsr2), $slot, ModoAsignacionCita::ProfesionalConcreto, $this->tsr);
            $this->fail('Intervención no da citas directas');
        } catch (AuthorizationException) {
        }

        $solicitud = app(SolicitudCitaService::class)->crear($this->datosJuan($this->tsr2), $this->tsr);
        $eventos = CitaEvento::count();

        try {
            $this->citacion()->citar($solicitud, $slot, ModoAsignacionCita::ProfesionalConcreto, $this->tsr);
            $this->fail('Intervención no cita desde una solicitud');
        } catch (AuthorizationException) {
        }

        $this->assertSame(0, Cita::count());
        $this->assertSame(1, SolicitudCita::count(), 'Solo la solicitud, que sí puede crear');
        $this->assertSame($eventos, CitaEvento::count());
    }

    /**
     * TF-CIT-26 — Un profesional no reprograma ni cancela sus citas. [negativo]
     *
     * @return void
     */
    #[Test]
    public function un_profesional_no_reprograma_ni_cancela_sus_citas(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');

        foreach ([
            fn () => $this->citacion()->reprogramar($cita, $this->slotDe($this->tsr, '2026-10-08', '11:00'), PedidoPor::Profesional, 'Me viene mal', $this->tsr),
            fn () => $this->citacion()->cancelar($cita, PedidoPor::Profesional, 'Me viene mal', $this->tsr),
        ] as $accion) {
            try {
                $accion();
                $this->fail('El profesional no mueve sus citas');
            } catch (AuthorizationException) {
            }
        }

        $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
    }

    /**
     * TF-CIT-27 — Auxiliar con doble rol: cita en su agenda, no cancela las suyas,
     * sí las de otros. [negativo]
     *
     * @return void
     */
    #[Test]
    public function el_auxiliar_cita_en_su_agenda_pero_no_cancela_las_suyas(): void
    {
        $propia = $this->citacion()->citarDirecto($this->datosJuan($this->auxiliar), $this->slotDe($this->auxiliar, '2026-10-07', '11:00'), ModoAsignacionCita::ProfesionalConcreto, $this->auxiliar);
        $this->assertSame($this->auxiliar->id, $propia->profesional_id);

        try {
            $this->citacion()->cancelar($propia, PedidoPor::Ciudadano, 'No viene', $this->auxiliar);
            $this->fail('No cancela citas de su propia agenda');
        } catch (AuthorizationException) {
        }

        $ajena = $this->citaConfirmada($this->juan, $this->tsr2, '2026-10-08', '11:00', $this->tipoInformacion);
        $this->citacion()->cancelar($ajena, PedidoPor::Ciudadano, 'No viene', $this->auxiliar);

        $this->assertSame(EstadoCita::Confirmada, $propia->fresh()->estado);
        $this->assertSame(EstadoCita::Cancelada, $ajena->fresh()->estado);
    }

    /**
     * TF-CIT-28 — Pedir un cambio abre un mensaje al supervisor con enlace a la
     * cita y deja evento; la cita no cambia.
     *
     * @return void
     */
    #[Test]
    public function pedir_cambio_al_supervisor(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');

        app(AtencionCitaService::class)->solicitarCambio($cita, 'Tengo formación esa mañana', $this->tsr);

        $hilo = MensajeHilo::sole();
        $this->assertSame('cita', $hilo->contexto_tipo);
        $this->assertSame($cita->id, (int) $hilo->contexto_id);
        $this->assertSame($this->tsr->id, $hilo->creado_por_id);
        $this->assertTrue($hilo->participantes()->where('usuario_id', $this->supervisor->id)->exists());
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::CambioSolicitado)->count());
        $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
        $this->assertSame('2026-10-07', $cita->fresh()->fecha->toDateString());

        // Otro profesional no pide cambios sobre citas ajenas
        $this->expectException(AuthorizationException::class);
        app(AtencionCitaService::class)->solicitarCambio($cita, 'No es mía', $this->tsr2);
    }

    /**
     * La supervisión sí mueve citas de su centro, también las de otros profesionales.
     *
     * @return void
     */
    #[Test]
    public function la_supervision_reprograma_citas_del_centro(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07');

        $nueva = $this->citacion()->reprogramar($cita, $this->slotDe($this->tsr, '2026-10-09', '11:00'), PedidoPor::Centro, 'Reorganización', $this->supervisor);

        $this->assertSame(EstadoCita::Confirmada, $nueva->estado);
    }
}
