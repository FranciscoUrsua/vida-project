<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Enums\PedidoPor;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PF-05.1, PF-05.5 y PF-06.2 de docs/modulo-agenda.md, reescritos para la fase
 * de citas: la cita se crea, se completa y se cancela a través de los servicios,
 * no escribiendo en el modelo (docs/instrucciones-cli/2026-09-citas-tests.md,
 * «Revisión de tests de Agenda»).
 */
class RevisionAgendaTest extends TestCase
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
     * Datos de una cita directa para María con el TSR.
     *
     * @return array<string, mixed>
     */
    private function datosCitaMaria(): array
    {
        return [
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'ordinaria',
            'destino' => 'profesional_concreto',
            'profesional_destino_id' => $this->tsr->id,
        ];
    }

    /**
     * PF-05.1 — La cita interna la crea quien da citas, con solicitud, y reserva el slot.
     *
     * @return void
     */
    #[Test]
    public function pf_05_1_la_cita_interna_se_crea_por_citacion_y_reserva_el_slot(): void
    {
        $slot = $this->slotDe($this->tsr, '2026-10-07', '11:00');

        $cita = $this->citacion()->citarDirecto($this->datosCitaMaria(), $slot, ModoAsignacionCita::ProfesionalConcreto, $this->consulta);

        $this->assertSame(EstadoCita::Confirmada, $cita->estado);
        $this->assertSame(OrigenCita::Interno, $cita->origen);
        $this->assertNotNull($cita->solicitud_cita_id);
        $this->assertSame(EstadoSlot::Reservado, $slot->fresh()->estado);
    }

    /**
     * PF-05.1 (negativo) — Quien solo tiene intervención no da citas.
     *
     * @return void
     */
    #[Test]
    public function pf_05_1_intervencion_no_da_citas(): void
    {
        $slot = $this->slotDe($this->tsr, '2026-10-07', '11:00');

        $this->expectException(AuthorizationException::class);

        try {
            $this->citacion()->citarDirecto($this->datosCitaMaria(), $slot, ModoAsignacionCita::ProfesionalConcreto, $this->tsr);
        } finally {
            $this->assertSame(EstadoSlot::Disponible, $slot->fresh()->estado);
        }
    }

    /**
     * PF-05.5 — La cita se completa al vincular un apunte, con marca de tiempo;
     * ya no hay apunte automático por tipo de slot.
     *
     * @return void
     */
    #[Test]
    public function pf_05_5_la_cita_se_completa_con_el_apunte(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '09:00');
        $this->assertNull($cita->completada_en);

        Apunte::create([
            'historia_id' => $this->historiaMaria->id,
            'autor_id' => $this->tsr->id,
            'fecha' => today()->toDateString(),
            'tipo' => TipoApunte::Entrevista,
            'contenido' => 'Entrevista de seguimiento',
            'visibilidad' => VisibilidadApunte::Profesionales,
            'cita_id' => $cita->id,
        ]);

        $cita->refresh();
        $this->assertSame(EstadoCita::Completada, $cita->estado);
        $this->assertNotNull($cita->completada_en);
        $this->assertSame(1, Apunte::where('cita_id', $cita->id)->count(), 'Solo el apunte del profesional: nada automático');
        $this->assertFalse(Schema::hasColumn('tipos_slot', 'genera_apunte_automatico'));
    }

    /**
     * PF-06.2 — La cancelación a petición de la persona la registra quien da
     * citas; el slot futuro vuelve a estar disponible.
     *
     * @return void
     */
    #[Test]
    public function pf_06_2_la_cancelacion_del_ciudadano_la_registra_citacion(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07', '11:00');

        $this->citacion()->cancelar($cita, PedidoPor::Ciudadano, 'Se ha mudado', $this->consulta);

        $cita->refresh();
        $this->assertSame(EstadoCita::Cancelada, $cita->estado);
        $this->assertSame(PedidoPor::Ciudadano, $cita->pedido_por_cancelacion);
        $this->assertSame(EstadoSlot::Disponible, $cita->slot->fresh()->estado);
    }

    /**
     * PF-06.2 (negativo) — El profesional de la cita no la cancela.
     *
     * @return void
     */
    #[Test]
    public function pf_06_2_el_profesional_no_cancela_su_cita(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-07', '11:00');

        $this->expectException(AuthorizationException::class);

        try {
            $this->citacion()->cancelar($cita, PedidoPor::Ciudadano, 'Se ha mudado', $this->tsr);
        } finally {
            $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
        }
    }
}
