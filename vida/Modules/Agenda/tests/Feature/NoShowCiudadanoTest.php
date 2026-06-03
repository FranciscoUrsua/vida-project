<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoShowCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    /** Datos mínimos para crear una Cita sobre un slot dado. */
    private function crearCita(Slot $slot, array $override = []): Cita
    {
        return Cita::create(array_merge([
            'slot_id' => $slot->id,
            'ciudadano_id' => Ciudadano::factory()->create()->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id' => $slot->tipo_slot_id,
            'centro_id' => $slot->centro_id,
            'fecha' => $slot->fecha->toDateString(),
            'hora_inicio' => $slot->hora_inicio,
            'hora_fin' => $slot->hora_fin,
            'estado' => EstadoCita::Confirmada->value,
            'origen' => OrigenCita::Interno->value,
        ], $override));
    }

    // =========================================================================
    // PF-06.1 — El profesional puede registrar el no-show del ciudadano
    // =========================================================================

    #[Test]
    public function test_pf_06_1_no_show_ciudadano_no_libera_slot(): void
    {
        // Slot cuya hora ya ha pasado (ayer)
        $slot = Slot::factory()->create([
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '10:45',
        ]);

        $cita = $this->crearCita($slot);
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);

        $cita->noShowCiudadano();

        $this->assertEquals(EstadoCita::NoShowCiudadano, $cita->fresh()->estado);
        $this->assertEquals(
            EstadoSlot::Reservado,
            $slot->fresh()->estado,
            'El slot no debe liberarse al registrar el no-show; permanece reservado hasta que el job lo expire'
        );
    }

    // =========================================================================
    // PF-06.2 — Cancelación anticipada del ciudadano libera el slot
    // =========================================================================

    #[Test]
    public function test_pf_06_2_cancelacion_anticipada_libera_slot(): void
    {
        // El ciudadano cancela con antelación; la cita es mañana a las 16:00
        $slot = Slot::factory()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '16:00',
            'hora_fin' => '16:45',
        ]);

        $cita = $this->crearCita($slot);
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);

        $supervisor = User::factory()->create();
        $cita->cancelar($supervisor, 'Cancelación solicitada por el ciudadano');

        $this->assertEquals(EstadoCita::Cancelada, $cita->fresh()->estado);
        $this->assertStringContainsString('ciudadano', $cita->fresh()->motivo_cancelacion);
        $this->assertEquals(
            EstadoSlot::Disponible,
            $slot->fresh()->estado,
            'El slot con hora futura debe volver a disponible para que pueda reasignarse'
        );
    }

    // =========================================================================
    // PF-06.3 — No-show en el momento no libera el slot; expira al final del día
    // =========================================================================

    #[Test]
    public function test_pf_06_3_no_show_en_momento_slot_expira(): void
    {
        // La cita era hoy pero la hora ya ha pasado (el profesional esperó y lo registra)
        $slot = Slot::factory()->create([
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00', // hora ya transcurrida cualquiera que sea la hora del test
            'hora_fin' => '08:45',
        ]);

        $cita = $this->crearCita($slot);
        $this->assertEquals(EstadoSlot::Reservado, $slot->fresh()->estado);

        $cita->noShowCiudadano();

        $this->assertEquals(EstadoCita::NoShowCiudadano, $cita->fresh()->estado);
        $this->assertEquals(
            EstadoSlot::Reservado,
            $slot->fresh()->estado,
            'El slot en franja en curso o pasada permanece reservado; el SlotExpirationJob lo transitará a no_ocupado'
        );
    }
}
