<?php

namespace Modules\Centro\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\DirectorCentro;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 9.7: Director del centro.
 *
 * @see docs/modulo-centros.md §9.7
 */
class DirectorCentroTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(): Centro
    {
        return Centro::create([
            'nombre' => 'Centro de prueba',
            'tipo_gestion' => 'municipal_directo',
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    private function crearProfesional(): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Coordinador/a'], ['activo' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true]
        );

        return Profesional::create([
            'nombre' => 'Director',
            'apellido1' => 'Prueba',
            'sexo' => 'M',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio' => today()->toDateString(),
            'activo' => true,
        ]);
    }

    // =========================================================================
    // TC-01 — Un centro tiene un único director activo
    // =========================================================================

    #[Test]
    public function un_centro_tiene_un_unico_director_activo(): void
    {
        $centro = $this->crearCentro();
        $profesional = $this->crearProfesional();

        // Director con fecha_fin pasada (inactivo).
        DirectorCentro::create([
            'centro_id' => $centro->id,
            'profesional_id' => $profesional->id,
            'fecha_inicio' => today()->subYear()->toDateString(),
            'fecha_fin' => today()->subMonth()->toDateString(),
        ]);

        // Director sin fecha_fin (activo actual).
        $directorActual = DirectorCentro::create([
            'centro_id' => $centro->id,
            'nombre' => 'Director Externo Actual',
            'email' => 'actual@centro.org',
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => null,
        ]);

        $activo = $centro->directorActivo();

        $this->assertNotNull($activo);
        $this->assertEquals($directorActual->id, $activo->id);
    }

    // =========================================================================
    // TC-02 — Al nombrar nuevo director, el anterior recibe fecha_fin
    // =========================================================================

    #[Test]
    public function al_nombrar_nuevo_director_el_anterior_recibe_fecha_fin(): void
    {
        $centro = $this->crearCentro();

        // Crear director A (activo).
        $centro->nombrarDirector([
            'nombre' => 'Director A',
            'email' => 'a@centro.org',
            'fecha_inicio' => today()->subMonth()->toDateString(),
        ]);

        $directorA = $centro->directorActivo();
        $this->assertNull($directorA->fecha_fin);

        // Nombrar director B cierra el cargo de A.
        $centro->nombrarDirector([
            'nombre' => 'Director B',
            'email' => 'b@centro.org',
        ]);

        $this->assertNotNull($directorA->fresh()->fecha_fin);
        $directorB = $centro->directorActivo();
        $this->assertNotNull($directorB);
        $this->assertEquals('Director B', $directorB->nombre);
        $this->assertNull($directorB->fecha_fin);
    }

    // =========================================================================
    // TC-03 — Director externo no puede tener profesional_id
    // =========================================================================

    #[Test]
    public function director_externo_no_puede_tener_profesional_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $centro = $this->crearCentro();
        $profesional = $this->crearProfesional();

        // Proporcionar tanto profesional_id como datos de contacto externo debe fallar.
        DirectorCentro::create([
            'centro_id' => $centro->id,
            'profesional_id' => $profesional->id,
            'nombre' => 'Director Externo',
            'email' => 'externo@centro.org',
            'fecha_inicio' => today()->toDateString(),
        ]);
    }
}
