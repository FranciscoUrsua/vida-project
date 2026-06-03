<?php

namespace Modules\Centro\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Espacio;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Red;
use Modules\Centro\Models\TipoEspacio;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.3: Red de centros.
 *
 * @see docs/modulo-centros.md §9.3
 */
class RedCentrosTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(string $nombre = 'Centro de prueba'): Centro
    {
        return Centro::create([
            'nombre' => $nombre,
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    private function crearRed(array $override = []): Red
    {
        return Red::create(array_merge([
            'nombre' => 'Red de prueba',
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ], $override));
    }

    /**
     * Crea un centro con n plazas libres en una colección activa.
     */
    private function crearCentroConPlazasLibres(int $numPlazas, string $nombre = 'Centro'): Centro
    {
        $centro = $this->crearCentro($nombre);

        $tipoEspacio = TipoEspacio::create(['nombre' => 'Dormitorio '.uniqid(), 'activo' => true]);

        $coleccion = ColeccionPlazas::create([
            'centro_id' => $centro->id,
            'nombre' => 'Plazas',
            'tipo_plaza' => 'pernocta',
            'modo_acceso' => 'libre',
            'capacidad' => $numPlazas,
            'activa' => true,
            'fecha_alta' => today()->toDateString(),
        ]);

        $espacio = Espacio::create([
            'coleccion_plazas_id' => $coleccion->id,
            'tipo_espacio_id' => $tipoEspacio->id,
            'nombre' => 'Sala A',
            'capacidad' => $numPlazas,
            'accesible' => false,
        ]);

        for ($i = 1; $i <= $numPlazas; $i++) {
            Plaza::create([
                'espacio_id' => $espacio->id,
                'nombre' => "Plaza $i",
                'estado' => 'libre',
                'activa' => true,
            ]);
        }

        return $centro;
    }

    // =========================================================================
    // TC-01 — Una red puede crearse sin centros
    // =========================================================================

    #[Test]
    public function una_red_puede_crearse_sin_centros(): void
    {
        $red = $this->crearRed();

        $this->assertTrue($red->exists);
        $this->assertEquals(0, $red->centros()->count());
    }

    // =========================================================================
    // TC-02 — Un centro puede unirse a una red
    // =========================================================================

    #[Test]
    public function un_centro_puede_unirse_a_una_red(): void
    {
        $red = $this->crearRed();
        $centro = $this->crearCentro();

        $red->centros()->attach($centro);

        $this->assertEquals(1, $red->centros()->count());
    }

    // =========================================================================
    // TC-03 — Un centro puede pertenecer a varias redes
    // =========================================================================

    #[Test]
    public function un_centro_puede_pertenecer_a_varias_redes(): void
    {
        $centro = $this->crearCentro();
        $redA = $this->crearRed(['nombre' => 'Red A']);
        $redB = $this->crearRed(['nombre' => 'Red B']);

        $redA->centros()->attach($centro);
        $redB->centros()->attach($centro);

        $this->assertEquals(2, $centro->redes()->count());
    }

    // =========================================================================
    // TC-04 — Una red agrega plazas libres de sus centros
    // =========================================================================

    #[Test]
    public function una_red_agrega_plazas_libres_de_sus_centros(): void
    {
        $red = $this->crearRed();
        $centroA = $this->crearCentroConPlazasLibres(3, 'Centro A');
        $centroB = $this->crearCentroConPlazasLibres(3, 'Centro B');

        $red->centros()->attach([$centroA->id, $centroB->id]);

        $this->assertEquals(6, $red->plazasLibresTotal());
    }

    // =========================================================================
    // TC-05 — Una red inactiva no aparece en consultas de disponibilidad
    // =========================================================================

    #[Test]
    public function una_red_inactiva_no_aparece_en_consultas_de_disponibilidad(): void
    {
        $this->crearRed(['nombre' => 'Red inactiva', 'activa' => false]);

        $redes = Red::activas()->get();

        $this->assertEquals(0, $redes->count());
    }

    // =========================================================================
    // TC-06 — Desligar un centro de una red no elimina el centro
    // =========================================================================

    #[Test]
    public function desligar_un_centro_de_una_red_no_elimina_el_centro(): void
    {
        $red = $this->crearRed();
        $centro = $this->crearCentro();
        $red->centros()->attach($centro);

        $red->centros()->detach($centro);

        $this->assertEquals(0, $red->centros()->count());
        $this->assertNotNull(Centro::find($centro->id));
    }
}
