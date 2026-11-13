<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales: los usuarios y sus adscripciones no se borran (RN-20 de
 * docs/modulo-tipo-centro.md, 13 nov 2026). Sustituye a los tests del borrado
 * lógico de usuarios (decisiones-tecnicas §13), que ya no se puede hacer.
 *
 * Las cuentas borradas antes de RN-20 conservan su `deleted_at` y siguen ocultas
 * (decisión del desarrollador): se simulan con una actualización directa.
 */
class UsuarioSinBorradoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function borrar_un_usuario_se_rechaza_y_el_usuario_sigue_sin_deleted_at(): void
    {
        $usuario = User::factory()->create();

        try {
            $usuario->delete();
            $this->fail('El borrado de un usuario debe rechazarse.');
        } catch (LogicException) {
        }

        $this->assertNotSoftDeleted('users', ['id' => $usuario->id]);
    }

    #[Test]
    public function el_borrado_fisico_de_un_usuario_tambien_se_rechaza(): void
    {
        $usuario = User::factory()->create();

        $this->expectException(LogicException::class);
        $usuario->forceDelete();
    }

    #[Test]
    public function una_adscripcion_no_se_borra(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'CSS Prueba', 'tipo' => 'centro', 'activa' => true]);
        $usuario = User::factory()->create();
        $adscripcion = UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => today(),
        ]);

        try {
            $adscripcion->delete();
            $this->fail('El borrado de una adscripción debe rechazarse.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('usuario_uo', ['id' => $adscripcion->id]);
    }

    #[Test]
    public function una_cuenta_borrada_antes_de_rn_20_sigue_oculta_y_su_email_puede_reutilizarse(): void
    {
        $antigua = User::factory()->create(['email' => 'reutilizable@vida360.test']);
        DB::table('users')->where('id', $antigua->id)->update(['deleted_at' => now()]);

        $this->assertNull(User::find($antigua->id));

        $nuevo = User::factory()->create(['email' => 'reutilizable@vida360.test']);

        $this->assertDatabaseCount('users', 2);
        $this->assertNotEquals($antigua->id, $nuevo->id);
    }

    #[Test]
    public function dos_usuarios_no_borrados_no_pueden_compartir_email(): void
    {
        User::factory()->create(['email' => 'unico@vida360.test']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'unico@vida360.test']);
    }
}
