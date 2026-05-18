<?php

namespace Modules\Centro\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Models\Centro;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.2: Ámbito territorial.
 *
 * @see docs/modulo-centros.md §9.2
 */
class AmbitoTerritorialTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(): Centro
    {
        return Centro::create([
            'nombre'       => 'Centro de prueba',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta'   => today()->toDateString(),
        ]);
    }

    // =========================================================================
    // TC-01 — Un centro puede tener ámbito ciudad_completa
    // =========================================================================

    #[Test]
    public function un_centro_puede_tener_ambito_ciudad_completa(): void
    {
        $centro = $this->crearCentro();

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'ciudad_completa',
            'descripcion' => 'Todo el municipio',
        ]);

        $this->assertEquals(1, $centro->ambitosTeritoriales()->count());
    }

    // =========================================================================
    // TC-02 — ciudad_completa no puede coexistir con otros ámbitos
    // =========================================================================

    #[Test]
    public function ciudad_completa_no_puede_coexistir_con_otros_ambitos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $centro = $this->crearCentro();

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'ciudad_completa',
            'descripcion' => 'Todo el municipio',
        ]);

        // Intentar añadir un segundo ámbito de cualquier tipo debe lanzar excepción.
        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'demarcacion_oficial',
            'descripcion' => 'Distrito de Arganzuela',
        ]);
    }

    // =========================================================================
    // TC-03 — Un centro puede tener múltiples ámbitos de demarcación
    // =========================================================================

    #[Test]
    public function un_centro_puede_tener_multiples_ambitos_de_demarcacion(): void
    {
        $centro = $this->crearCentro();

        AmbitoTerritorial::create([
            'centro_id'    => $centro->id,
            'tipo'         => 'demarcacion_oficial',
            'descripcion'  => 'Distrito de Arganzuela',
            'referencia_id' => 1,
        ]);

        AmbitoTerritorial::create([
            'centro_id'    => $centro->id,
            'tipo'         => 'demarcacion_oficial',
            'descripcion'  => 'Distrito de Retiro',
            'referencia_id' => 2,
        ]);

        $this->assertEquals(2, $centro->ambitosTeritoriales()->count());
    }

    // =========================================================================
    // TC-04 — Un centro puede combinar tipos de ámbito
    // =========================================================================

    #[Test]
    public function un_centro_puede_combinar_tipos_de_ambito(): void
    {
        $centro = $this->crearCentro();

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'demarcacion_oficial',
            'descripcion' => 'Distrito de Arganzuela',
        ]);

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'barrios',
            'descripcion' => 'Barrio de Lavapiés',
        ]);

        $this->assertEquals(2, $centro->ambitosTeritoriales()->count());
    }

    // =========================================================================
    // TC-05 — Un ámbito tipo poligono_gis requiere geojson
    // =========================================================================

    #[Test]
    public function un_ambito_tipo_poligono_requiere_geojson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $centro = $this->crearCentro();

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'poligono_gis',
            'descripcion' => 'Polígono sur',
            'geojson'     => null,
        ]);
    }

    // =========================================================================
    // TC-06 — Eliminar un ámbito no afecta al centro
    // =========================================================================

    #[Test]
    public function eliminar_un_ambito_no_afecta_al_centro(): void
    {
        $centro = $this->crearCentro();

        $a1 = AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'demarcacion_oficial',
            'descripcion' => 'Distrito A',
        ]);

        AmbitoTerritorial::create([
            'centro_id'   => $centro->id,
            'tipo'        => 'demarcacion_oficial',
            'descripcion' => 'Distrito B',
        ]);

        $a1->delete();

        $this->assertTrue($centro->fresh()->activo ?? true);
        $this->assertEquals(1, $centro->fresh()->ambitosTeritoriales()->count());
    }
}
