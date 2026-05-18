<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CitaCicloVidaTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de Prueba'): Centro
    {
        return Centro::create([
            'nombre'       => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => now()->toDateString(),
        ]);
    }

    // =========================================================================
    // PF-05.1 — Crear una cita interna cambia el slot a reservado
    // =========================================================================

    #[Test]
    public function test_pf_05_1_crear_cita_interna_cambia_slot_a_reservado(): void
    {
        $this->markTestIncomplete(
            'PF-05.1: pendiente de implementar lógica que actualiza slot.estado al crear Cita'
        );
    }

    // =========================================================================
    // PF-05.2 — Cita desde API externa registra la referencia externa
    // =========================================================================

    #[Test]
    public function test_pf_05_2_cita_externa_registra_referencia(): void
    {
        $this->markTestIncomplete(
            'PF-05.2: pendiente de implementar lógica de Cita desde api_externa con validación de origen_permitido'
        );
    }

    // =========================================================================
    // PF-05.3 — No se puede crear una cita sobre un slot ya reservado
    // =========================================================================

    #[Test]
    public function test_pf_05_3_no_cita_sobre_slot_reservado(): void
    {
        // La tabla citas tiene unique constraint sobre slot_id (FK unique).
        // Crear una segunda cita sobre el mismo slot viola esa restricción.
        $slot = Slot::factory()->reservado()->create();

        $ciudadano   = \App\Models\Ciudadano::factory()->create();
        $profesional = User::factory()->create();

        // Primera cita existente
        Cita::create([
            'slot_id'        => $slot->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id'   => $slot->tipo_slot_id,
            'centro_id'      => $slot->centro_id,
            'fecha'          => $slot->fecha,
            'hora_inicio'    => $slot->hora_inicio,
            'hora_fin'       => $slot->hora_fin,
            'estado'         => EstadoCita::Confirmada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Segunda cita sobre el mismo slot: viola la restricción unique de slot_id
        Cita::create([
            'slot_id'        => $slot->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $slot->usuario_id,
            'tipo_slot_id'   => $slot->tipo_slot_id,
            'centro_id'      => $slot->centro_id,
            'fecha'          => $slot->fecha,
            'hora_inicio'    => $slot->hora_inicio,
            'hora_fin'       => $slot->hora_fin,
            'estado'         => EstadoCita::Confirmada->value,
            'origen'         => OrigenCita::Interno->value,
        ]);
    }

    // =========================================================================
    // PF-05.4 — No se puede crear una cita sobre un slot de urgencia desde canal externo
    // =========================================================================

    #[Test]
    public function test_pf_05_4_no_cita_externa_sobre_slot_urgencia(): void
    {
        $this->markTestIncomplete(
            'PF-05.4: pendiente de implementar validación de origen_permitido al crear Cita'
        );
    }

    // =========================================================================
    // PF-05.5 — Marcar cita como completada registra el timestamp
    // =========================================================================

    #[Test]
    public function test_pf_05_5_completar_cita_registra_timestamp(): void
    {
        $this->markTestIncomplete(
            'PF-05.5: pendiente de implementar flujo de completado con evento hacia módulo Intervención'
        );
    }

    // =========================================================================
    // PF-05.6 — Cancelar una cita activa libera el slot
    // =========================================================================

    #[Test]
    public function test_pf_05_6_cancelar_cita_libera_slot(): void
    {
        $this->markTestIncomplete(
            'PF-05.6: pendiente de implementar servicio de cancelación que actualiza slot.estado'
        );
    }

    // =========================================================================
    // PF-05.7 — Cancelación retroactiva de cita pasada deja el slot en no_ocupado
    // =========================================================================

    #[Test]
    public function test_pf_05_7_cancelacion_retroactiva_slot_queda_no_ocupado(): void
    {
        $this->markTestIncomplete(
            'PF-05.7: pendiente de implementar flujo de cancelación retroactiva'
        );
    }

    // =========================================================================
    // PF-05.8 — Cancelación retroactiva informa de apuntes antes de confirmar
    // =========================================================================

    #[Test]
    public function test_pf_05_8_cancelacion_retroactiva_informa_apuntes(): void
    {
        $this->markTestIncomplete(
            'PF-05.8: pendiente de implementar consulta de apuntes previos a cancelación retroactiva'
        );
    }
}
