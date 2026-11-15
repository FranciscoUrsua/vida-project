<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use LogicException;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\SolicitudParaGestor;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\OrigenActualizacion;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;
use Modules\Prestaciones\Services\Gestor\AdaptadorGestorExpedientes;
use Modules\Prestaciones\Services\Gestor\GestorManual;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo C — Envío (TF-SOL-16 a 20): documentos obligatorios, simulador, registro manual,
 * tramitación interna sin adaptador y fallo del gestor (RN-05, §3.2 del diseño).
 */
class EnvioTest extends TestCase
{
    use RefreshDatabase;
    use SolicitudesTestSetup;

    /**
     * Escenario de solicitudes.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->montarSolicitudes();
    }

    /** TF-SOL-16 — Faltan documentos obligatorios: pide justificación y la guarda en el evento. */
    #[Test]
    public function tf_sol_16_faltan_documentos_obligatorios(): void
    {
        $solicitud = $this->sadEnPreparacion();

        try {
            $this->servicio()->enviar($solicitud, $this->tramitadora1);
            $this->fail('Sin justificación debía rechazarse');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Documento de identidad', $e->getMessage());
        }

        $this->servicio()->enviar($solicitud, $this->tramitadora1, 'La persona lo trae en la cita');

        $evento = $solicitud->eventos()->get()->last();
        $this->assertSame(AccionEventoSolicitud::Enviada, $evento->accion);
        $this->assertSame('La persona lo trae en la cita', $evento->detalle['justificacion']);
        $this->assertSame(['Documento de identidad'], $evento->detalle['documentos_pendientes']);
    }

    /** TF-SOL-17 — Envío con simulador: número de expediente, fecha y origen usuario. */
    #[Test]
    public function tf_sol_17_envio_con_simulador(): void
    {
        $solicitud = $this->sadEnviada();

        $this->assertSame(EstadoSolicitudPrestacion::Enviada, $solicitud->estado);
        $this->assertSame('SIM-'.$solicitud->id, $solicitud->numero_expediente);
        $this->assertSame(today()->toDateString(), $solicitud->fecha_envio->toDateString());
        $this->assertSame(OrigenActualizacion::Usuario, $solicitud->eventos()->get()->last()->origen);
        $this->assertSame([$solicitud->id], $this->gestor->iniciadas);
    }

    /** TF-SOL-18 — Con el adaptador manual hay que indicar número y fecha de presentación. */
    #[Test]
    public function tf_sol_18_envio_con_adaptador_manual(): void
    {
        $this->usarGestor(new GestorManual);
        $solicitud = $this->sadEnPreparacion();

        try {
            $this->servicio()->enviar($solicitud, $this->tramitadora1, 'Sin DNI');
            $this->fail('Sin número de expediente debía rechazarse');
        } catch (DomainException) {
            $this->assertSame(EstadoSolicitudPrestacion::EnPreparacion, $solicitud->fresh()->estado);
        }

        $this->servicio()->enviar($solicitud, $this->tramitadora1, 'Sin DNI', 'EXP-2026/123', Carbon::parse('2026-10-30'));

        $solicitud->refresh();
        $this->assertSame([EstadoSolicitudPrestacion::Enviada, 'EXP-2026/123', '2026-10-30'], [$solicitud->estado, $solicitud->numero_expediente, $solicitud->fecha_envio->toDateString()]);
    }

    /** TF-SOL-19 — Tramitación interna: se envía sin llamar al adaptador. */
    #[Test]
    public function tf_sol_19_tramitacion_interna_sin_adaptador(): void
    {
        $this->usarGestor(new class implements AdaptadorGestorExpedientes
        {
            public function iniciarSolicitud(SolicitudParaGestor $solicitud): string
            {
                throw new LogicException('No debía llamarse al gestor');
            }

            public function consultarEstado(string $expediente): ActualizacionExpediente
            {
                throw new LogicException('No debía llamarse al gestor');
            }

            public function aportarDocumentacion(string $expediente, array $documentoIds): void
            {
                throw new LogicException('No debía llamarse al gestor');
            }

            public function admite(string $operacion): bool
            {
                throw new LogicException('No debía llamarse al gestor');
            }

            public function nombre(): string
            {
                return 'doble';
            }
        });

        $solicitud = $this->servicio()->crear($this->ana, $this->pInterna, $this->tsNorte, null, null, $this->cssNorte);
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);
        $this->servicio()->tomar($solicitud->fresh(), $this->tramitadoraCentro);
        $this->servicio()->enviar($solicitud->fresh(), $this->tramitadoraCentro);

        $this->assertSame(EstadoSolicitudPrestacion::Enviada, $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->numero_expediente);
    }

    /** TF-SOL-20 — Si el gestor falla, sigue en preparación, el usuario ve el mensaje y la causa queda en el log. */
    #[Test]
    public function tf_sol_20_fallo_del_gestor(): void
    {
        Log::spy();
        $this->gestor->fallarEn(AdaptadorGestorExpedientes::INICIAR);
        $solicitud = $this->sadEnPreparacion();

        Livewire::actingAs($this->tramitadora1)->test(SolicitudPrestacionPage::class, ['solicitud' => $solicitud])
            ->call('abrirPanel', 'enviar')
            ->set('justificacion', 'Sin DNI')
            ->call('confirmar')
            ->assertHasErrors('accion')
            ->assertSee('El gestor de expedientes no ha respondido');

        $this->assertSame(EstadoSolicitudPrestacion::EnPreparacion, $solicitud->fresh()->estado);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, 'fallo programado'));
    }
}
