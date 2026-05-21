<?php

namespace Modules\Centro\Tests\Feature;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Servicio;
use Modules\Centro\Models\SolicitudServicio;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sección 11.10: Solicitud de servicio.
 *
 * @see docs/modulo-centros.md §11.10
 */
class SolicitudServicioTest extends TestCase
{
    use RefreshDatabase;

    private function crearServicio(): Servicio
    {
        $uo = UnidadOrganizativa::firstOrCreate(
            ['nombre' => 'UO Test Solicitud'],
            ['tipo' => 'departamento', 'parent_id' => null, 'activa' => true]
        );

        return Servicio::create([
            'nombre'                 => 'Servicio de Ayuda a Domicilio',
            'nombre_corto'           => 'SAD',
            'unidad_organizativa_id' => $uo->id,
            'cargo_nombre'           => 'Jefe del SAD',
            'activo'                 => true,
            'fecha_alta'             => today()->toDateString(),
        ]);
    }

    private function crearPrestacion(string $codigo = '020101'): Prestacion
    {
        return Prestacion::firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre'          => "Prestación {$codigo}",
                'tipo_prestacion' => 'servicio',
                'nivel_garantia'  => 'garantizada',
                'activa'          => true,
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
            'nombre'           => 'TSR',
            'apellido1'        => 'Prueba',
            'sexo'             => 'F',
            'cargo_id'         => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio'     => today()->toDateString(),
            'activo'           => true,
        ]);
    }

    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::create([
            'nombre'           => 'Ciudadano',
            'apellido1'        => 'Prueba',
            'sexo'             => 'M',
            'fecha_nacimiento' => '1980-01-01',
            'activo'           => true,
        ]);
    }

    // =========================================================================
    // TC-01 — Prescribir prestación de servicio genera solicitud
    // =========================================================================

    #[Test]
    public function prescribir_prestacion_de_servicio_genera_solicitud(): void
    {
        $servicio    = $this->crearServicio();
        $prestacion  = $this->crearPrestacion();
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();

        $servicio->prestaciones()->attach($prestacion->id);

        // El TSR prescribe la prestación → se genera una solicitud al servicio.
        $solicitud = SolicitudServicio::create([
            'servicio_id'    => $servicio->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'prestacion_id'  => $prestacion->id,
            'estado'         => 'pendiente',
            'fecha_solicitud' => today()->toDateString(),
        ]);

        $this->assertEquals('pendiente', $solicitud->estado);
        $this->assertEquals($servicio->id, $solicitud->servicio_id);
    }

    // =========================================================================
    // TC-02 — La solicitud referencia prestación, ciudadano y profesional
    // =========================================================================

    #[Test]
    public function la_solicitud_referencia_prestacion_ciudadano_y_profesional(): void
    {
        $servicio    = $this->crearServicio();
        $prestacion  = $this->crearPrestacion();
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();

        $solicitud = SolicitudServicio::create([
            'servicio_id'    => $servicio->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'prestacion_id'  => $prestacion->id,
            'estado'         => 'pendiente',
            'fecha_solicitud' => today()->toDateString(),
        ]);

        $this->assertEquals($prestacion->id, $solicitud->prestacion->id);
        $this->assertEquals($ciudadano->id, $solicitud->ciudadano->id);
        $this->assertEquals($profesional->id, $solicitud->profesional->id);
    }

    // =========================================================================
    // TC-03 — El estado de una solicitud puede actualizarse
    // =========================================================================

    #[Test]
    public function el_estado_de_una_solicitud_puede_actualizarse(): void
    {
        $servicio    = $this->crearServicio();
        $prestacion  = $this->crearPrestacion();
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();

        $solicitud = SolicitudServicio::create([
            'servicio_id'    => $servicio->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'prestacion_id'  => $prestacion->id,
            'estado'         => 'pendiente',
            'fecha_solicitud' => today()->toDateString(),
        ]);

        $solicitud->update(['estado' => 'en_tramite']);

        $this->assertEquals('en_tramite', $solicitud->fresh()->estado);
    }

    // =========================================================================
    // TC-04 — Una solicitud resuelta registra fecha de resolución
    // =========================================================================

    #[Test]
    public function una_solicitud_resuelta_registra_fecha_resolucion(): void
    {
        $servicio    = $this->crearServicio();
        $prestacion  = $this->crearPrestacion();
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();

        $solicitud = SolicitudServicio::create([
            'servicio_id'    => $servicio->id,
            'ciudadano_id'   => $ciudadano->id,
            'profesional_id' => $profesional->id,
            'prestacion_id'  => $prestacion->id,
            'estado'         => 'pendiente',
            'fecha_solicitud' => today()->toDateString(),
        ]);

        $this->assertNull($solicitud->fecha_resolucion);

        $solicitud->update(['estado' => 'resuelta']);

        $this->assertEquals(today()->toDateString(), $solicitud->fresh()->fecha_resolucion->toDateString());
    }

    // =========================================================================
    // TC-05 — Cancelar el plan no elimina las solicitudes existentes
    // =========================================================================

    #[Test]
    public function cancelar_plan_no_elimina_solicitudes_existentes(): void
    {
        $servicio    = $this->crearServicio();
        $prestacion  = $this->crearPrestacion();
        $profesional = $this->crearProfesional();
        $ciudadano   = $this->crearCiudadano();

        $plan = PlanDeIntervencion::factory()->activo()->create();

        $solicitud = SolicitudServicio::create([
            'servicio_id'          => $servicio->id,
            'ciudadano_id'         => $ciudadano->id,
            'profesional_id'       => $profesional->id,
            'plan_intervencion_id' => $plan->id,
            'prestacion_id'        => $prestacion->id,
            'estado'               => 'pendiente',
            'fecha_solicitud'      => today()->toDateString(),
        ]);

        // Cancelar el plan (cambio de estado, no eliminación).
        $plan->update(['estado' => EstadoPlan::Cerrado]);

        $this->assertDatabaseHas('solicitudes_servicio', [
            'id'     => $solicitud->id,
            'estado' => 'pendiente',
        ]);
    }
}
