<?php

namespace Modules\Agenda\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\GestionAusenciaService;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoShowProfesionalTest extends TestCase
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
    // PF-07.1 — Registrar ausencia sobrevenida cancela citas del día
    // =========================================================================

    #[Test]
    public function test_pf_07_1_ausencia_cancela_citas_con_motivo(): void
    {
        $this->markTestIncomplete(
            'PF-07.1: pendiente de implementar GestionAusenciaService::procesarAusencia()'
        );
    }

    // =========================================================================
    // PF-07.2 — La reasignación a slot de urgencia crea el registro de reasignación
    // =========================================================================

    #[Test]
    public function test_pf_07_2_reasignacion_a_urgencia_crea_registro(): void
    {
        $this->markTestIncomplete(
            'PF-07.2: pendiente de implementar GestionAusenciaService::reasignar()'
        );
    }

    // =========================================================================
    // PF-07.3 — Sin slots de urgencia disponibles devuelve colección vacía
    // =========================================================================

    #[Test]
    public function test_pf_07_3_sin_urgencias_disponibles_devuelve_vacio(): void
    {
        $this->markTestIncomplete(
            'PF-07.3: pendiente de implementar GestionAusenciaService::procesarAusencia()'
        );
    }

    // =========================================================================
    // PF-07.4 — En modo básico la reasignación usa slots ordinarios
    // =========================================================================

    #[Test]
    public function test_pf_07_4_modo_basico_usa_slots_ordinarios(): void
    {
        $this->markTestIncomplete(
            'PF-07.4: pendiente de implementar lógica de modo básico en GestionAusenciaService'
        );
    }

    // =========================================================================
    // PF-07.5 — Una excepción posterior anula las líneas y slots materializados
    // =========================================================================

    #[Test]
    public function test_pf_07_5_excepcion_posterior_anula_lineas_y_slots(): void
    {
        $this->markTestIncomplete(
            'PF-07.5: pendiente de implementar lógica de anulación de slots por ExcepcionProfesional posterior'
        );
    }
}
