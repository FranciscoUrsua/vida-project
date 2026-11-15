<?php

namespace Modules\Prestaciones\Tests\Feature\Solicitudes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Intervencion\Models\Apunte;
use Modules\Mensajes\Models\Alerta;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\LineaResolucionDto;
use Modules\Prestaciones\Data\ResolucionDto;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Enums\SentidoResolucion;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Tests\Concerns\SolicitudesTestSetup;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grupo E — Resolución (TF-SOL-29 a 31 y 36; RN-08 y RN-09): la resolución se incorpora
 * sola, con prestaciones concedidas, apunte automático y aviso al trabajador social.
 */
class ResolucionTest extends TestCase
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

    /** TF-SOL-29 — Resolución favorable: concedida con condiciones y plan, apunte con fuente y aviso. */
    #[Test]
    public function tf_sol_29_resolucion_favorable(): void
    {
        $solicitud = $this->enviadaDesdeElPlan();

        $this->resolver($solicitud, SentidoResolucion::Favorable, [new LineaResolucionDto($this->pSad->id, true, intensidad: '20 h/mes')]);

        $this->assertSame(EstadoSolicitudPrestacion::Resuelta, $solicitud->fresh()->estado);
        $pp = PrestacionPersona::sole();
        $this->assertSame([EstadoPrestacionPersona::Concedida, '2026-11-25', '20 h/mes', $this->pisoAna->id, $this->pSad->id],
            [$pp->estado, $pp->fecha_concesion->toDateString(), $pp->condiciones['intensidad'], $pp->plan_id, $pp->prestacion_id]);
        $this->assertTrue(Apunte::withoutGlobalScopes()->where('historia_id', $this->historiaAna->id)->where('fuente', 'gestor_expedientes')->exists());
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_type', SolicitudPrestacion::class)->where('titulo', 'Solicitud resuelta')->exists());
    }

    /** TF-SOL-30 — Parcial y distinta de lo pedido: una concedida de otra prestación y una denegada visible. */
    #[Test]
    public function tf_sol_30_resolucion_parcial_y_distinta(): void
    {
        $solicitud = $this->sadEnviada();

        $this->resolver($solicitud, SentidoResolucion::Parcial, [
            new LineaResolucionDto($this->pInterna->id, true, intensidad: '10 h/mes'),
            new LineaResolucionDto($this->pSad->id, false, 'No alcanza el grado de dependencia'),
        ]);

        $this->assertSame([$this->pInterna->id], PrestacionPersona::pluck('prestacion_id')->all());
        Livewire::actingAs($this->tsNorte)->test(SolicitudPrestacionPage::class, ['solicitud' => $solicitud->fresh()])
            ->assertSee('No alcanza el grado de dependencia')
            ->assertSee('Parcialmente favorable');
    }

    /** TF-SOL-31 — Resolución desfavorable: ninguna prestación; aviso al TS. */
    #[Test]
    public function tf_sol_31_resolucion_desfavorable(): void
    {
        $solicitud = $this->sadEnviada();

        $this->resolver($solicitud, SentidoResolucion::Desfavorable, [new LineaResolucionDto($this->pSad->id, false, 'No cumple requisitos')]);

        $this->assertSame(0, PrestacionPersona::count());
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_id', $solicitud->id)->where('titulo', 'Solicitud resuelta')->exists());
    }

    /** TF-SOL-36 — Estado externo «desistida»: cerrada sin resolución, con motivo, sin prestaciones y con aviso. */
    #[Test]
    public function tf_sol_36_cerrar_sin_resolucion(): void
    {
        $solicitud = $this->sadEnviada();

        $this->gestor->simularAviso($solicitud, new ActualizacionExpediente('DESISTIDO', 'Desistimiento del interesado', Carbon::parse('2026-11-12')));

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitudPrestacion::CerradaSinResolucion, $solicitud->estado);
        $this->assertStringContainsString('Desistimiento del interesado', $solicitud->motivo_cierre);
        $this->assertSame(0, PrestacionPersona::count());
        $this->assertTrue(Alerta::pendientesPara($this->tsNorte)->where('origen_id', $solicitud->id)->where('titulo', 'Solicitud cerrada sin resolución')->exists());
    }

    /**
     * Solicitud de SAD desde un objetivo del PISO de Ana, ya enviada.
     *
     * @return SolicitudPrestacion
     */
    private function enviadaDesdeElPlan(): SolicitudPrestacion
    {
        $solicitud = $this->servicio()->crear($this->ana, $this->pSad, $this->tsNorte, $this->pisoAna, $this->objetivoAna1, $this->cssNorte);
        $this->servicio()->pasarABandeja($solicitud, $this->tsNorte);
        $this->servicio()->tomar($solicitud->fresh(), $this->tramitadora1);
        $this->servicio()->enviar($solicitud->fresh(), $this->tramitadora1, 'Sin DNI');

        return $solicitud->fresh();
    }

    /**
     * Llega la resolución por aviso del gestor.
     *
     * @param SolicitudPrestacion $solicitud
     * @param SentidoResolucion $sentido
     * @param list<LineaResolucionDto> $lineas
     *
     * @return void
     */
    private function resolver(SolicitudPrestacion $solicitud, SentidoResolucion $sentido, array $lineas): void
    {
        $fecha = Carbon::parse('2026-11-25');
        $this->gestor->simularAviso($solicitud, new ActualizacionExpediente('RESUELTO', 'Resuelto', $fecha, resolucion: new ResolucionDto($fecha, $sentido, $lineas)));
    }
}
