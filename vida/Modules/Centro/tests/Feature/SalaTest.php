<?php

namespace Modules\Centro\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centro\Models\Actividad;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\Sala;
use Modules\Centro\Models\SesionActividad;
use Modules\Centro\Models\TipoActividad;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales — Sala y TipoActividad (slug).
 *
 * @see docs/modulo-centros.md §4.7 §4.8 §4.9
 */
class SalaTest extends TestCase
{
    use RefreshDatabase;

    private function crearCentro(): Centro
    {
        return Centro::create([
            'nombre'         => 'Centro de prueba',
            'tipo_gestion'   => 'municipal_directo',
            'inscripcion_libre' => false,
            'fecha_alta'     => today()->toDateString(),
        ]);
    }

    private function crearSesion(Centro $centro): SesionActividad
    {
        $tipo = TipoActividad::create(['nombre' => 'Tipo ' . uniqid(), 'activo' => true]);
        $actividad = Actividad::create([
            'centro_id'                  => $centro->id,
            'tipo_actividad_id'          => $tipo->id,
            'nombre'                     => 'Actividad prueba',
            'modo_acceso'                => 'libre',
            'requiere_inscripcion_centro' => false,
            'activa'                     => true,
            'fecha_alta'                 => today()->toDateString(),
        ]);

        return SesionActividad::create([
            'actividad_id' => $actividad->id,
            'fecha'        => today()->toDateString(),
            'hora_inicio'  => '10:00:00',
            'estado'       => 'programada',
        ]);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-01
    // -------------------------------------------------------------------------

    #[Test]
    public function se_puede_crear_una_sala_con_campos_minimos(): void
    {
        $centro = $this->crearCentro();

        $sala = Sala::create([
            'centro_id' => $centro->id,
            'nombre'    => 'Aula A',
        ]);

        $this->assertDatabaseHas('salas', [
            'id'        => $sala->id,
            'nombre'    => 'Aula A',
            'accesible' => false,
            'activa'    => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-02
    // -------------------------------------------------------------------------

    #[Test]
    public function una_sala_pertenece_a_un_centro(): void
    {
        $centro = $this->crearCentro();
        $sala   = Sala::create(['centro_id' => $centro->id, 'nombre' => 'Aula B']);

        $this->assertEquals($centro->id, $sala->centro->id);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-03
    // -------------------------------------------------------------------------

    #[Test]
    public function un_centro_puede_tener_multiples_salas(): void
    {
        $centro = $this->crearCentro();

        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Sala 1']);
        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Sala 2']);
        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Sala 3']);

        $this->assertEquals(3, $centro->salas()->count());
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-04
    // -------------------------------------------------------------------------

    #[Test]
    public function scope_activas_excluye_las_inactivas(): void
    {
        $centro = $this->crearCentro();

        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Activa 1', 'activa' => true]);
        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Activa 2', 'activa' => true]);
        Sala::create(['centro_id' => $centro->id, 'nombre' => 'Inactiva', 'activa' => false]);

        $resultado = Sala::activas()->where('centro_id', $centro->id)->get();

        $this->assertCount(2, $resultado);
        $this->assertTrue($resultado->every(fn (Sala $s) => $s->activa));
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-05
    // -------------------------------------------------------------------------

    #[Test]
    public function se_puede_asignar_una_sala_a_una_sesion(): void
    {
        $centro  = $this->crearCentro();
        $sala    = Sala::create(['centro_id' => $centro->id, 'nombre' => 'Aula C']);
        $sesion  = $this->crearSesion($centro);

        $sesion->sala_id = $sala->id;
        $sesion->save();

        $this->assertEquals($sala->id, $sesion->fresh()->sala->id);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-06
    // -------------------------------------------------------------------------

    #[Test]
    public function sala_id_en_sesion_es_nullable(): void
    {
        $centro = $this->crearCentro();
        $sesion = $this->crearSesion($centro);

        $this->assertNull($sesion->sala_id);

        $sesion->save();

        $this->assertNull($sesion->fresh()->sala_id);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-SALA-07
    // -------------------------------------------------------------------------

    #[Test]
    public function eliminar_sala_pone_sala_id_a_null_en_sus_sesiones(): void
    {
        $centro = $this->crearCentro();
        $sala   = Sala::create(['centro_id' => $centro->id, 'nombre' => 'Aula D']);
        $sesion = $this->crearSesion($centro);

        $sesion->sala_id = $sala->id;
        $sesion->save();

        $sala->delete();

        $this->assertNull($sesion->fresh()->sala_id);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-TIPO-01
    // -------------------------------------------------------------------------

    #[Test]
    public function tipo_actividad_requiere_slug_unico(): void
    {
        TipoActividad::create(['nombre' => 'Taller', 'slug' => 'taller', 'activo' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TipoActividad::create(['nombre' => 'Otro taller', 'slug' => 'taller', 'activo' => true]);
    }

    // -------------------------------------------------------------------------
    // TF-CEN-TIPO-02
    // -------------------------------------------------------------------------

    #[Test]
    public function tipo_actividad_inactivo_no_aparece_en_scope_activos(): void
    {
        TipoActividad::create(['nombre' => 'Activo',   'slug' => 'activo-tipo',   'activo' => true]);
        TipoActividad::create(['nombre' => 'Inactivo', 'slug' => 'inactivo-tipo', 'activo' => false]);

        $resultado = TipoActividad::activos()->get();

        $this->assertTrue($resultado->every(fn (TipoActividad $t) => $t->activo));
        $this->assertFalse($resultado->contains(fn (TipoActividad $t) => $t->slug === 'inactivo-tipo'));
    }
}
