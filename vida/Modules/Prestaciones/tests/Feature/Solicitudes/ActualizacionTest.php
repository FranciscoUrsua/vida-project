<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Mensajes\Models\Alerta;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\IncidenciaExpediente;
use Modules\Prestaciones\Data\LineaResolucionDto;
use Modules\Prestaciones\Data\ResolucionDto;
use Modules\Prestaciones\Enums\AccionEventoSolicitud;
use Modules\Prestaciones\Enums\OrigenActualizacion;
use Modules\Prestaciones\Enums\SentidoResolucion;
use Modules\Prestaciones\Http\Livewire\BandejaTramitacionPage;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Services\Gestor\GestorManual;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo D — Actualizaciones del expediente (TF-SOL-21 a 28; RN-06 y RN-07).
 */
class ActualizacionTest extends TestCase
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

    /** TF-SOL-21 — Cuatro orígenes: aviso, consulta automática, consulta manual y registro manual. */
    #[Test]
    public function tf_sol_21_cuatro_origenes(): void
    {
        $solicitud = $this->sadEnviada();
        $exp = $solicitud->numero_expediente;

        $this->gestor->simularAviso($solicitud, new ActualizacionExpediente('TRAMITE_01', 'Registrada', Carbon::parse('2026-11-03')));

        $this->gestor->programar($exp, new ActualizacionExpediente('TRAMITE_02', 'En estudio', Carbon::parse('2026-11-04')));
        Artisan::call('expedientes:consultar');

        $this->gestor->programar($exp, new ActualizacionExpediente('TRAMITE_02', 'En estudio', Carbon::parse('2026-11-05')));
        $this->servicio()->consultarAhora($solicitud->fresh(), $this->tsNorte);

        $this->servicio()->registrarManual($solicitud->fresh(), new ActualizacionExpediente(null, 'Pendiente de informe', Carbon::parse('2026-11-06'), estadoNormalizado: 'en_tramite'), $this->tramitadora1);

        $origenes = $solicitud->eventos()->where('accion', AccionEventoSolicitud::EstadoActualizado->value)->get()->map(fn ($e) => $e->origen)->all();
        $this->assertSame([OrigenActualizacion::AvisoGestor, OrigenActualizacion::ConsultaAutomatica, OrigenActualizacion::ConsultaManual, OrigenActualizacion::RegistroManual], $origenes);
        $this->assertSame(['en_tramite', 'Pendiente de informe'], [$solicitud->fresh()->estado_externo_normalizado, $solicitud->fresh()->estado_externo_texto]);
    }

    /** TF-SOL-22 — El mismo aviso dos veces deja un solo evento. [negativo] */
    #[Test]
    public function tf_sol_22_idempotencia(): void
    {
        $solicitud = $this->sadEnviada();
        $aviso = new ActualizacionExpediente('TRAMITE_01', 'Registrada', Carbon::parse('2026-11-03'));

        $this->gestor->simularAviso($solicitud, $aviso);
        $this->gestor->simularAviso($solicitud, $aviso);

        $this->assertSame(1, $solicitud->eventos()->where('accion', AccionEventoSolicitud::EstadoActualizado->value)->count());
    }

    /** TF-SOL-23 — El estado externo se guarda tal cual y se normaliza por configuración. */
    #[Test]
    public function tf_sol_23_mapeo_de_estados(): void
    {
        $solicitud = $this->sadEnviada();

        $this->gestor->simularAviso($solicitud, new ActualizacionExpediente('TRAMITE_02', 'En estudio técnico', Carbon::parse('2026-11-03')));

        $solicitud->refresh();
        $this->assertSame(['TRAMITE_02', 'En estudio técnico', 'en_tramite'], [$solicitud->estado_externo_codigo, $solicitud->estado_externo_texto, $solicitud->estado_externo_normalizado]);
    }

    /** TF-SOL-24 — Incidencia con plazo: evento, avisos al TS y a la responsable, y en «con incidencia abierta». */
    #[Test]
    public function tf_sol_24_incidencia(): void
    {
        $solicitud = $this->sadEnviada();

        $this->gestor->simularAviso($solicitud, $this->requerimiento());

        $evento = $solicitud->eventos()->where('accion', AccionEventoSolicitud::Incidencia->value)->sole();
        $this->assertSame(['requerimiento_documentacion', '2026-11-20'], [$evento->detalle['tipo'], $evento->detalle['plazo']]);
        foreach ([$this->tsNorte, $this->tramitadora1] as $destinatario) {
            $this->assertTrue(Alerta::pendientesPara($destinatario)->where('origen_type', SolicitudPrestacion::class)->where('origen_id', $solicitud->id)->exists());
        }

        $this->actingAs($this->tramitadora2);
        $this->assertSame([$solicitud->id], Livewire::test(BandejaTramitacionPage::class)->call('verPestana', 'incidencias')->get('solicitudes')->pluck('id')->all());
    }

    /** TF-SOL-25 — Atender la incidencia: documento, aportación al gestor y fuera de «con incidencia abierta». */
    #[Test]
    public function tf_sol_25_atender_incidencia(): void
    {
        $solicitud = $this->sadEnviada();
        $this->gestor->simularAviso($solicitud, $this->requerimiento());
        $incidencia = $solicitud->incidenciasAbiertas()->sole();
        $dni = $this->documentoEnCarpeta($this->ana, $this->tipoIdentidad);

        $this->servicio()->atenderIncidencia($solicitud->fresh(), $incidencia->id, $this->tramitadora1, [$dni->id]);

        $this->assertTrue($solicitud->eventos()->where('accion', AccionEventoSolicitud::IncidenciaAtendida->value)->exists());
        $this->assertSame([['expediente' => $solicitud->numero_expediente, 'documentos' => [$dni->id]]], $this->gestor->aportaciones);
        $this->assertCount(0, $solicitud->fresh()->incidenciasAbiertas());
        $this->assertFalse(SolicitudPrestacion::conIncidenciaAbierta()->whereKey($solicitud->id)->exists());
    }

    /** TF-SOL-26 — VIDA no escribe al ciudadano por la tramitación. */
    #[Test]
    public function tf_sol_26_vida_no_escribe_al_ciudadano(): void
    {
        Notification::fake();
        Mail::fake();
        $solicitud = $this->sadEnviada();

        $this->gestor->simularAviso($solicitud, $this->requerimiento());
        $this->gestor->simularAviso($solicitud->fresh(), new ActualizacionExpediente('RESUELTO', 'Resuelto', Carbon::parse('2026-11-25'),
            resolucion: new ResolucionDto(Carbon::parse('2026-11-25'), SentidoResolucion::Favorable, [new LineaResolucionDto($this->pSad->id, true, intensidad: '20 h/mes')])));

        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    /** TF-SOL-27 — Con el adaptador manual no hay consulta automática ni «Consultar ahora». */
    #[Test]
    public function tf_sol_27_consulta_automatica_solo_con_adaptador_que_la_admite(): void
    {
        $this->usarGestor(new GestorManual);
        $solicitud = $this->sadEnPreparacion();
        $this->servicio()->enviar($solicitud, $this->tramitadora1, 'Sin DNI', 'EXP-1', Carbon::today());

        Artisan::call('expedientes:consultar');

        $this->assertSame(0, $solicitud->eventos()->where('accion', AccionEventoSolicitud::EstadoActualizado->value)->count());
        Livewire::actingAs($this->tsNorte)->test(SolicitudPrestacionPage::class, ['solicitud' => $solicitud->fresh()])->assertDontSee('Consultar ahora');
    }

    /** TF-SOL-28 — Un fallo en una solicitud no para la consulta automática de las demás. */
    #[Test]
    public function tf_sol_28_un_fallo_no_para_la_consulta(): void
    {
        Log::spy();
        $solicitudes = [$this->sadEnviada(), $this->sadEnviada(), $this->sadEnviada()];
        foreach ($solicitudes as $s) {
            $this->gestor->programar($s->numero_expediente, new ActualizacionExpediente('TRAMITE_02', 'En estudio', Carbon::parse('2026-11-04')));
        }
        $this->gestor->fallarEn($solicitudes[1]->numero_expediente);

        Artisan::call('expedientes:consultar');

        $this->assertSame(['TRAMITE_02', null, 'TRAMITE_02'], array_map(fn ($s) => $s->fresh()->estado_externo_codigo, $solicitudes));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $m) => str_contains($m, (string) $solicitudes[1]->numero_expediente));
    }

    /**
     * Requerimiento de documentación con plazo.
     *
     * @return ActualizacionExpediente
     */
    private function requerimiento(): ActualizacionExpediente
    {
        return new ActualizacionExpediente(null, null, Carbon::parse('2026-11-10'), [
            new IncidenciaExpediente('requerimiento_documentacion', Carbon::parse('2026-11-10'), 'Aportar el DNI vigente', Carbon::parse('2026-11-20'), 'INC-1'),
        ]);
    }
}
