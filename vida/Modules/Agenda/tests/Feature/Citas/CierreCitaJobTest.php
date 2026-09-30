<?php

namespace Modules\Agenda\Tests\Feature\Citas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Jobs\CitaCierreJob;
use Modules\Agenda\Jobs\SlotExpirationJob;
use Modules\Agenda\Models\CitaEvento;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Agenda\Tests\Concerns\CitasTestSetup;
use Modules\Mensajes\Models\AlertaDestinatario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Grupo G: citas sin cerrar (TF-CIT-37 y 38) y solicitudes fuera de plazo.
 *
 * @see docs/instrucciones-cli/2026-09-citas-tests.md
 */
class CierreCitaJobTest extends TestCase
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
     * Ejecuta el cierre del día y la expiración de slots, como el scheduler.
     *
     * @return void
     */
    private function finDelDia(): void
    {
        app()->call([new CitaCierreJob(), 'handle']);
        (new SlotExpirationJob())->handle();
    }

    /**
     * Avisos recibidos por un usuario con un título.
     *
     * @param \App\Models\User $usuario
     * @param string $titulo
     * @return int
     */
    private function avisos($usuario, string $titulo): int
    {
        return AlertaDestinatario::where('usuario_id', $usuario->id)->whereHas('alerta', fn ($q) => $q->where('titulo', $titulo))->count();
    }

    /**
     * TF-CIT-37 — La cita de esta mañana sin apunte queda pendiente de cierre, sigue
     * confirmada y su slot no pasa a no ocupado. [negativo]
     *
     * @return void
     */
    #[Test]
    public function la_cita_sin_apunte_queda_pendiente_de_cierre(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '09:00');

        $this->finDelDia();

        $cita->refresh();
        $this->assertSame(EstadoCita::Confirmada, $cita->estado);
        $this->assertTrue($cita->pendiente_cierre);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::MarcadaPendienteCierre)->count());
        $this->assertSame(1, $this->avisos($this->tsr, 'Cita pendiente de cierre'));

        // Al día siguiente el job de expiración recorre los slots de ayer: este no se toca
        Carbon::setTestNow('2026-10-07 20:00:00');
        $this->finDelDia();

        $this->assertSame(EstadoSlot::Reservado, $cita->slot->fresh()->estado);
        $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
        $this->assertSame(1, CitaEvento::where('cita_id', $cita->id)->where('accion', AccionCitaEvento::MarcadaPendienteCierre)->count(), 'Se marca una sola vez');
    }

    /**
     * TF-CIT-38 — Pendiente más de 3 días laborables: un solo aviso a supervisión.
     *
     * @return void
     */
    #[Test]
    public function el_supervisor_recibe_un_solo_aviso(): void
    {
        $cita = $this->citaConfirmada($this->maria, $this->tsr, '2026-10-06', '09:00');

        Carbon::setTestNow('2026-10-06 20:00:00');
        $this->finDelDia();

        // 7, 8, 9 y 12: cuatro días laborables después
        Carbon::setTestNow('2026-10-12 20:00:00');
        $this->finDelDia();
        Carbon::setTestNow('2026-10-13 20:00:00');
        $this->finDelDia();

        $this->assertSame(1, $this->avisos($this->supervisor, 'Cita sin cerrar'));
        $this->assertSame(EstadoCita::Confirmada, $cita->fresh()->estado);
    }

    /**
     * Una solicitud abierta pasada su fecha límite avisa a supervisión una vez.
     *
     * @return void
     */
    #[Test]
    public function la_solicitud_fuera_de_plazo_avisa_una_vez(): void
    {
        app(SolicitudCitaService::class)->crear([
            'ciudadano_id' => $this->maria->id,
            'centro_id' => $this->centro->id,
            'tipo_cita_id' => $this->tipoSeguimiento->id,
            'urgencia' => 'urgente',
            'destino' => 'referencia',
        ], $this->tsr);

        Carbon::setTestNow('2026-10-09 20:00:00');
        $this->finDelDia();
        Carbon::setTestNow('2026-10-12 20:00:00');
        $this->finDelDia();

        $this->assertSame(1, $this->avisos($this->supervisor, 'Solicitud de cita fuera de plazo'));
    }
}
