<?php

namespace Modules\Agenda\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfesionalItineranteTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // PF-09.1 — Itinerante no tiene disponibilidad en el centro que no le corresponde ese día
    // =========================================================================

    #[Test]
    public function test_pf_09_1_itinerante_sin_disponibilidad_en_centro_equivocado(): void
    {
        $this->markTestIncomplete(
            'PF-09.1: pendiente de implementar DisponibilidadService::obtenerSlots()'
        );
    }

    // =========================================================================
    // PF-09.2 — Excepción de un centro no afecta disponibilidad en el otro
    // =========================================================================

    #[Test]
    public function test_pf_09_2_excepcion_de_un_centro_no_afecta_al_otro(): void
    {
        $this->markTestIncomplete(
            'PF-09.2: pendiente de implementar DisponibilidadService con filtro de centro por excepción'
        );
    }
}
