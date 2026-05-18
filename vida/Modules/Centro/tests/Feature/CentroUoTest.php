<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.1: Centro y Unidad Organizativa.
 *
 * @see docs/modulo-centros.md §9.1
 */
class CentroUoTest extends TestCase
{
    use RefreshDatabase;

    private function crearUo(string $nombre = 'UO de prueba'): UnidadOrganizativa
    {
        return UnidadOrganizativa::create([
            'nombre'    => $nombre,
            'tipo'      => 'departamento',
            'parent_id' => null,
            'activa'    => true,
        ]);
    }

    private function crearCentro(array $override = []): Centro
    {
        return Centro::create(array_merge([
            'nombre'       => 'Centro de prueba',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => today()->toDateString(),
        ], $override));
    }

    // =========================================================================
    // TC-01 — Un centro puede pertenecer a una UO
    // =========================================================================

    #[Test]
    public function un_centro_puede_pertenecer_a_una_uo(): void
    {
        $uo     = $this->crearUo('UO Atención Primaria');
        $centro = $this->crearCentro(['unidad_organizativa_id' => $uo->id]);

        $this->assertEquals($uo->id, $centro->unidadOrganizativa->id);
    }

    // =========================================================================
    // TC-02 — Un centro puede existir sin UO
    // =========================================================================

    #[Test]
    public function un_centro_puede_existir_sin_uo(): void
    {
        $centro = $this->crearCentro(['unidad_organizativa_id' => null]);

        $this->assertTrue($centro->exists);
        $this->assertNull($centro->unidad_organizativa_id);
        $this->assertNull($centro->unidadOrganizativa);
    }

    // =========================================================================
    // TC-03 — La UO de un centro puede cambiarse
    // =========================================================================

    #[Test]
    public function la_uo_de_un_centro_puede_cambiarse(): void
    {
        $uoA    = $this->crearUo('UO A');
        $uoB    = $this->crearUo('UO B');
        $centro = $this->crearCentro(['unidad_organizativa_id' => $uoA->id]);

        $centro->update(['unidad_organizativa_id' => $uoB->id]);

        $this->assertEquals($uoB->id, $centro->fresh()->unidadOrganizativa->id);
    }
}
