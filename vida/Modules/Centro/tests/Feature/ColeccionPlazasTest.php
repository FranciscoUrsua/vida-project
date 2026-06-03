<?php

namespace Modules\Centro\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\TipoEspacio;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.4: Colección de plazas y disponibilidad.
 *
 * @see docs/modulo-centros.md §9.4
 */
class ColeccionPlazasTest extends TestCase
{
    use RefreshDatabase;

    private function crearColeccion(array $override = []): ColeccionPlazas
    {
        $centro = Centro::create([
            'nombre' => 'Centro de prueba',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => today()->toDateString(),
        ]);

        return ColeccionPlazas::create(array_merge([
            'centro_id' => $centro->id,
            'nombre' => 'Colección de prueba',
            'tipo_plaza' => 'pernocta',
            'modo_acceso' => 'libre',
            'capacidad' => 10,
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ], $override));
    }

    private function crearPlazas(ColeccionPlazas $coleccion, int $libres, int $ocupadas, int $mantenimiento): void
    {
        $tipoEspacio = TipoEspacio::create(['nombre' => 'Tipo '.uniqid(), 'activo' => true]);

        $espacio = Espacio::create([
            'coleccion_plazas_id' => $coleccion->id,
            'tipo_espacio_id' => $tipoEspacio->id,
            'nombre' => 'Sala principal',
            'capacidad' => $libres + $ocupadas + $mantenimiento,
            'accesible' => false,
        ]);

        for ($i = 0; $i < $libres; $i++) {
            Plaza::create(['espacio_id' => $espacio->id, 'nombre' => "Libre $i", 'estado' => 'libre', 'activa' => true]);
        }
        for ($i = 0; $i < $ocupadas; $i++) {
            Plaza::create(['espacio_id' => $espacio->id, 'nombre' => "Ocupada $i", 'estado' => 'ocupada', 'activa' => true]);
        }
        for ($i = 0; $i < $mantenimiento; $i++) {
            Plaza::create(['espacio_id' => $espacio->id, 'nombre' => "Mant $i", 'estado' => 'mantenimiento', 'activa' => true]);
        }
    }

    // =========================================================================
    // TC-01 — La capacidad refleja el total de plazas
    // =========================================================================

    #[Test]
    public function la_capacidad_refleja_el_total_de_plazas(): void
    {
        $coleccion = $this->crearColeccion(['capacidad' => 10]);
        $this->crearPlazas($coleccion, 10, 0, 0);

        $this->assertEquals(10, $coleccion->plazas()->count());
    }

    // =========================================================================
    // TC-02 — Plazas disponibles excluye ocupadas y en mantenimiento
    // =========================================================================

    #[Test]
    public function plazas_disponibles_excluye_ocupadas_y_en_mantenimiento(): void
    {
        $coleccion = $this->crearColeccion(['capacidad' => 5]);
        $this->crearPlazas($coleccion, 2, 2, 1);

        $this->assertEquals(2, $coleccion->plazasDisponibles());
    }

    // =========================================================================
    // TC-03 — Una colección inactiva no ofrece plazas disponibles
    // =========================================================================

    #[Test]
    public function una_coleccion_inactiva_no_ofrece_plazas_disponibles(): void
    {
        $coleccion = $this->crearColeccion(['activa' => false, 'capacidad' => 3]);
        $this->crearPlazas($coleccion, 3, 0, 0);

        $this->assertEquals(0, $coleccion->plazasDisponibles());
    }
}
