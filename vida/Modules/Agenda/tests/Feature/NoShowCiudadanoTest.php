<?php

namespace Modules\Agenda\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoShowCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // PF-06.1 — El profesional puede registrar el no-show del ciudadano
    // =========================================================================

    #[Test]
    public function test_pf_06_1_no_show_ciudadano_no_libera_slot(): void
    {
        $this->markTestIncomplete(
            'PF-06.1: pendiente de implementar flujo de no-show del ciudadano'
        );
    }

    // =========================================================================
    // PF-06.2 — Cancelación anticipada del ciudadano libera el slot
    // =========================================================================

    #[Test]
    public function test_pf_06_2_cancelacion_anticipada_libera_slot(): void
    {
        $this->markTestIncomplete(
            'PF-06.2: pendiente de implementar flujo de cancelación anticipada por ciudadano'
        );
    }

    // =========================================================================
    // PF-06.3 — No-show en el momento no libera el slot; expira al final del día
    // =========================================================================

    #[Test]
    public function test_pf_06_3_no_show_en_momento_slot_expira(): void
    {
        $this->markTestIncomplete(
            'PF-06.3: pendiente de implementar flujo de no-show en el momento + SlotExpirationJob'
        );
    }
}
