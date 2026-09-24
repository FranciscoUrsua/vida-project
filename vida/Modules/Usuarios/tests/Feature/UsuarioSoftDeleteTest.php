<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests funcionales: borrar un usuario es un soft delete, nunca un borrado físico.
 *
 * Antes de este cambio, `$user->delete()` intentaba un DELETE físico sobre
 * `users` que lanzaba QueryException en cuanto el usuario tenía una fila en
 * `usuario_uo` (FK `usuario_id` con `onDelete('restrict')`) — también desde
 * el DeleteAction de UsuarioResource en Filament, que llama a delete().
 */
class UsuarioSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function borrar_un_usuario_con_adscripcion_a_uo_no_lanza_excepcion_y_es_soft_delete(): void
    {
        $uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Prueba',
            'tipo' => 'centro',
            'activa' => true,
        ]);
        $usuario = User::factory()->create();

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today(),
        ]);

        $usuario->delete();

        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
        // La adscripción no se ha borrado: el historial se conserva intacto
        $this->assertDatabaseHas('usuario_uo', ['usuario_id' => $usuario->id]);
    }

    #[Test]
    public function borrar_un_usuario_con_rol_asignado_no_lanza_excepcion(): void
    {
        $rol = Role::create(['name' => 'intervencion', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $usuario->delete();

        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
    }

    #[Test]
    public function un_usuario_borrado_desaparece_de_las_consultas_por_defecto_pero_sigue_accesible_con_withtrashed(): void
    {
        $usuario = User::factory()->create();
        $id = $usuario->id;

        $usuario->delete();

        $this->assertNull(User::find($id));
        $this->assertNotNull(User::withTrashed()->find($id));
        $this->assertNotNull(User::withTrashed()->find($id)->deleted_at);
    }

    #[Test]
    public function el_email_de_un_usuario_borrado_puede_reutilizarse_en_un_usuario_nuevo(): void
    {
        $usuario = User::factory()->create(['email' => 'reutilizable@vida360.test']);
        $usuario->delete();

        $nuevo = User::factory()->create(['email' => 'reutilizable@vida360.test']);

        $this->assertDatabaseCount('users', 2);
        $this->assertNotEquals($usuario->id, $nuevo->id);
    }

    #[Test]
    public function dos_usuarios_activos_no_pueden_compartir_email(): void
    {
        User::factory()->create(['email' => 'unico@vida360.test']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'unico@vida360.test']);
    }
}
