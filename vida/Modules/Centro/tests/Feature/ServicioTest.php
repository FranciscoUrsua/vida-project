<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Servicio;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 11.8: Servicio.
 *
 * @see docs/modulo-centros.md §11.8
 */
class ServicioTest extends TestCase
{
    use RefreshDatabase;

    private function crearUo(): UnidadOrganizativa
    {
        return UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Servicio'],
            ['tipo' => 'departamento', 'parent_id' => null, 'activa' => true]
        );
    }

    private function crearServicio(?UnidadOrganizativa $uo = null): Servicio
    {
        return Servicio::create([
            'nombre' => 'Servicio de Ayuda a Domicilio',
            'nombre_corto' => 'SAD',
            'unidad_organizativa_id' => ($uo ?? $this->crearUo())->id,
            'cargo_nombre' => 'Jefe de Servicio de Ayuda a Domicilio',
            'activo' => true,
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    private function crearPrestacion(string $codigo = '010101'): Prestacion
    {
        return Prestacion::firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => "Prestación {$codigo}",
                'tipo_prestacion' => 'servicio',
                'nivel_garantia' => 'garantizada',
                'activa' => true,
            ]
        );
    }

    private function crearProfesional(): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'Trabajador/a Social'], ['activo' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a de carrera'],
            ['es_externo' => false, 'activo' => true]
        );

        return Profesional::create([
            'nombre' => 'Test',
            'apellido1' => 'Profesional',
            'sexo' => 'M',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio' => today()->toDateString(),
            'activo' => true,
        ]);
    }

    // =========================================================================
    // TC-01 — Un servicio requiere UO
    // =========================================================================

    #[Test]
    public function un_servicio_requiere_uo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Servicio::create([
            'nombre' => 'Servicio Sin UO',
            'nombre_corto' => 'SSU',
            'cargo_nombre' => 'Jefe de Servicio',
            'fecha_alta' => today()->toDateString(),
        ]);
    }

    // =========================================================================
    // TC-02 — Un servicio tiene cargo definido
    // =========================================================================

    #[Test]
    public function un_servicio_tiene_cargo_definido(): void
    {
        $servicio = $this->crearServicio();

        $this->assertEquals('Jefe de Servicio de Ayuda a Domicilio', $servicio->cargo_nombre);
    }

    // =========================================================================
    // TC-03 — Un servicio puede tener múltiples prestaciones
    // =========================================================================

    #[Test]
    public function un_servicio_puede_tener_multiples_prestaciones(): void
    {
        $servicio = $this->crearServicio();
        $prestacion1 = $this->crearPrestacion('010101');
        $prestacion2 = $this->crearPrestacion('010102');
        $prestacion3 = $this->crearPrestacion('010103');

        $servicio->prestaciones()->attach([
            $prestacion1->id,
            $prestacion2->id,
            $prestacion3->id,
        ]);

        $this->assertEquals(3, $servicio->prestaciones()->count());
    }

    // =========================================================================
    // TC-04 — Un servicio no tiene plazas ni actividades
    // =========================================================================

    #[Test]
    public function un_servicio_no_tiene_plazas_ni_actividades(): void
    {
        $servicio = $this->crearServicio();

        $this->assertFalse(method_exists($servicio, 'coleccionesPlazas'));
        $this->assertFalse(method_exists($servicio, 'actividades'));
    }

    // =========================================================================
    // TC-05 — Un profesional puede asignarse a varios servicios
    // =========================================================================

    #[Test]
    public function un_profesional_puede_asignarse_a_varios_servicios(): void
    {
        $uo = $this->crearUo();
        $profesional = $this->crearProfesional();

        $servicioA = $this->crearServicio($uo);
        $servicioB = Servicio::create([
            'nombre' => 'Servicio de Dependencia',
            'nombre_corto' => 'DEP',
            'unidad_organizativa_id' => $uo->id,
            'cargo_nombre' => 'Jefe de Dependencia',
            'activo' => true,
            'fecha_alta' => today()->toDateString(),
        ]);

        $servicioA->profesionales()->attach($profesional->id, ['fecha_alta' => today()->toDateString()]);
        $servicioB->profesionales()->attach($profesional->id, ['fecha_alta' => today()->toDateString()]);

        $this->assertTrue($servicioA->profesionales()->where('profesionales.id', $profesional->id)->exists());
        $this->assertTrue($servicioB->profesionales()->where('profesionales.id', $profesional->id)->exists());
    }
}
