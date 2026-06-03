<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales del modelo de Unidades Organizativas y adscripción.
 *
 * Verifican la jerarquía de UO, las consultas de descendencia, el scoping
 * por ámbito de gestión, y el historial de adscripciones.
 *
 * Grupos:
 *   B — Jerarquía de UO (TF-USU-24 a TF-USU-28)
 *   C — Adscripción a UO (TF-USU-29 a TF-USU-31)
 */
class UnidadOrganizativaTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo_raiz;

    private UnidadOrganizativa $uo_hija;

    private UnidadOrganizativa $uo_nieta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uo_raiz = UnidadOrganizativa::create(['nombre' => 'Ayuntamiento', 'tipo' => 'ayuntamiento', 'activa' => true]);
        $this->uo_hija = UnidadOrganizativa::create(['nombre' => 'DG Servicios Sociales', 'tipo' => 'dg', 'parent_id' => $this->uo_raiz->id, 'activa' => true]);
        $this->uo_nieta = UnidadOrganizativa::create(['nombre' => 'CSS Arganzuela', 'tipo' => 'centro', 'parent_id' => $this->uo_hija->id, 'activa' => true]);
    }

    // -------------------------------------------------------------------------
    // Grupo B — Jerarquía de UO (TF-USU-24 a TF-USU-28)
    // -------------------------------------------------------------------------

    #[Test]
    public function una_uo_puede_tener_uo_hijas(): void
    {
        $hijas = $this->uo_raiz->children;

        $this->assertCount(1, $hijas);
        $this->assertTrue($hijas->contains($this->uo_hija));
    }

    #[Test]
    public function los_descendientes_de_una_uo_incluyen_todos_los_niveles_inferiores(): void
    {
        $descendientes = $this->uo_raiz->descendants;

        $this->assertTrue($descendientes->contains('id', $this->uo_hija->id));
        $this->assertTrue($descendientes->contains('id', $this->uo_nieta->id));
    }

    #[Test]
    public function un_usuario_adscrito_a_uo_padre_tiene_visibilidad_sobre_uo_hijas(): void
    {
        $supervisor = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $supervisor->id,
            'unidad_organizativa_id' => $this->uo_raiz->id,
            'fecha_inicio' => today(),
        ]);

        $this->assertTrue($supervisor->tieneAccesoGestionA($this->uo_nieta));
    }

    #[Test]
    public function un_usuario_no_tiene_visibilidad_sobre_uo_que_no_son_descendientes_suyas(): void
    {
        $uo_paralela = UnidadOrganizativa::create([
            'nombre' => 'DG Mayores',
            'tipo' => 'dg',
            'parent_id' => $this->uo_raiz->id,
            'activa' => true,
        ]);

        $admUsuarios = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $admUsuarios->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
            'fecha_inicio' => today(),
        ]);

        $this->assertFalse($admUsuarios->tieneAccesoGestionA($uo_paralela));
    }

    #[Test]
    public function desactivar_una_uo_no_elimina_las_adscripciones_existentes(): void
    {
        $profesional = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
            'fecha_inicio' => today(),
        ]);

        $this->uo_hija->update(['activa' => false]);

        $this->assertDatabaseHas('usuario_uo', [
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
        ]);
        $this->assertFalse($this->uo_hija->fresh()->activa);
    }

    // -------------------------------------------------------------------------
    // Grupo C — Adscripción a UO (TF-USU-29 a TF-USU-31)
    // -------------------------------------------------------------------------

    #[Test]
    public function un_usuario_puede_estar_adscrito_a_mas_de_una_uo_simultaneamente(): void
    {
        $profesional = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
            'fecha_inicio' => today(),
        ]);

        UsuarioUo::create([
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_nieta->id,
            'fecha_inicio' => today(),
        ]);

        $this->assertEquals(2, $profesional->unidadesOrganizativas()->count());
    }

    #[Test]
    public function la_adscripcion_tiene_fechas_de_vigencia_y_mantiene_historial(): void
    {
        $profesional = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
            'fecha_inicio' => today()->subYear(),
            'fecha_fin' => today()->subMonth(),
        ]);

        UsuarioUo::create([
            'usuario_id' => $profesional->id,
            'unidad_organizativa_id' => $this->uo_hija->id,
            'fecha_inicio' => today(),
            'fecha_fin' => null,
        ]);

        $registros = UsuarioUo::where('usuario_id', $profesional->id)->get();

        $this->assertCount(2, $registros);
        $this->assertNotNull($registros->firstWhere('fecha_fin', '!=', null)?->fecha_fin);
        $this->assertNull($registros->sortByDesc('fecha_inicio')->first()->fecha_fin);
    }

    #[Test]
    public function adm_usuarios_no_puede_adscribir_usuarios_a_uo_fuera_de_su_ambito(): void
    {
        $this->markTestIncomplete(
            'TF-USU-31: pendiente de implementar Policy/Service para autorización de adscripción. '.
            'No existe ningún mecanismo que impida crear un UsuarioUo desde código arbitrario.'
        );
    }
}
