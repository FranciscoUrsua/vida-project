<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventoAgendaTest extends TestCase
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
    // PF-08.1 — Crear un evento bloquea los slots disponibles de la franja
    // =========================================================================

    #[Test]
    public function test_pf_08_1_crear_evento_bloquea_slots_disponibles(): void
    {
        $this->markTestIncomplete(
            'PF-08.1: pendiente de implementar lógica de bloqueo de slots al crear EventoAgenda'
        );
    }

    // =========================================================================
    // PF-08.2 — Evento sobre una cita confirmada genera aviso pero no bloquea la cita
    // =========================================================================

    #[Test]
    public function test_pf_08_2_evento_sobre_cita_confirmada_genera_aviso(): void
    {
        $this->markTestIncomplete(
            'PF-08.2: pendiente de implementar detección de conflicto con citas al crear EventoAgenda'
        );
    }

    // =========================================================================
    // PF-08.3 — Dos eventos en el mismo espacio y franja generan aviso sin bloquear
    // =========================================================================

    #[Test]
    public function test_pf_08_3_conflicto_espacio_genera_aviso_sin_bloquear(): void
    {
        $this->markTestIncomplete(
            'PF-08.3: pendiente de implementar detección de conflicto de espacio en EventoAgenda'
        );
    }

    // =========================================================================
    // PF-08.4 — En modo básico el evento simplificado bloquea la franja sin gestión de espacios
    // =========================================================================

    #[Test]
    public function test_pf_08_4_modo_basico_evento_sin_espacio(): void
    {
        $this->markTestIncomplete(
            'PF-08.4: pendiente de implementar flujo de evento simplificado en modo básico'
        );
    }
}
