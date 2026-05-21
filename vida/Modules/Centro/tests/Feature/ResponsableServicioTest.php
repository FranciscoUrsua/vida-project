<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\ResponsableServicio;
use Modules\Centro\Models\Servicio;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 11.9: Responsable de servicio.
 *
 * @see docs/modulo-centros.md §11.9
 */
class ResponsableServicioTest extends TestCase
{
    use RefreshDatabase;

    private function crearServicio(string $cargoNombre = 'Jefe de Departamento X'): Servicio
    {
        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Responsable'],
            ['tipo' => 'departamento', 'parent_id' => null, 'activa' => true]
        );

        return Servicio::create([
            'nombre'                 => 'Servicio de Prueba',
            'nombre_corto'           => 'SDP',
            'unidad_organizativa_id' => $uo->id,
            'cargo_nombre'           => $cargoNombre,
            'activo'                 => true,
            'fecha_alta'             => today()->toDateString(),
        ]);
    }

    private function crearProfesional(string $nombre = 'Test'): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Trabajador/a Social'], ['activo' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true]
        );

        return Profesional::create([
            'nombre'           => $nombre,
            'apellido1'        => 'Apellido',
            'sexo'             => 'M',
            'cargo_id'         => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio'     => today()->toDateString(),
            'activo'           => true,
        ]);
    }

    // =========================================================================
    // TC-01 — Un servicio tiene un único responsable activo
    // =========================================================================

    #[Test]
    public function un_servicio_tiene_un_unico_responsable_activo(): void
    {
        $servicio     = $this->crearServicio();
        $profesional  = $this->crearProfesional();

        // Responsable inactivo (con fecha_fin pasada).
        ResponsableServicio::create([
            'servicio_id'    => $servicio->id,
            'profesional_id' => $profesional->id,
            'fecha_inicio'   => today()->subYear()->toDateString(),
            'fecha_fin'      => today()->subMonth()->toDateString(),
        ]);

        // Responsable activo (sin fecha_fin).
        $responsableActual = ResponsableServicio::create([
            'servicio_id'    => $servicio->id,
            'profesional_id' => $profesional->id,
            'fecha_inicio'   => today()->toDateString(),
            'fecha_fin'      => null,
        ]);

        $activo = $servicio->responsableActivo();

        $this->assertNotNull($activo);
        $this->assertEquals($responsableActual->id, $activo->id);
    }

    // =========================================================================
    // TC-02 — Al nombrar nuevo responsable, el anterior recibe fecha_fin
    // =========================================================================

    #[Test]
    public function al_nombrar_nuevo_responsable_el_anterior_recibe_fecha_fin(): void
    {
        $servicio     = $this->crearServicio();
        $profesionalA = $this->crearProfesional('Ana');
        $profesionalB = $this->crearProfesional('Berta');

        $servicio->nombrarResponsable($profesionalA);

        $responsableA = $servicio->responsableActivo();
        $this->assertNull($responsableA->fecha_fin);

        $servicio->nombrarResponsable($profesionalB);

        $this->assertNotNull($responsableA->fresh()->fecha_fin);

        $responsableB = $servicio->responsableActivo();
        $this->assertNotNull($responsableB);
        $this->assertEquals($profesionalB->id, $responsableB->profesional_id);
        $this->assertNull($responsableB->fecha_fin);
    }

    // =========================================================================
    // TC-03 — El responsable de servicio siempre es profesional de VIDA 360
    // =========================================================================

    #[Test]
    public function el_responsable_de_servicio_siempre_es_profesional_vida360(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $servicio = $this->crearServicio();

        ResponsableServicio::create([
            'servicio_id'    => $servicio->id,
            'profesional_id' => null,
            'fecha_inicio'   => today()->toDateString(),
        ]);
    }

    // =========================================================================
    // TC-04 — El cargo del responsable se toma del servicio
    // =========================================================================

    #[Test]
    public function el_cargo_del_responsable_se_toma_del_servicio(): void
    {
        $cargoEsperado = 'Jefe de Departamento X';
        $servicio      = $this->crearServicio($cargoEsperado);
        $profesional   = $this->crearProfesional();

        $responsable = $servicio->nombrarResponsable($profesional);

        $this->assertEquals($cargoEsperado, $responsable->cargo_nombre);
        $this->assertEquals($cargoEsperado, $servicio->cargo_nombre);
    }
}
