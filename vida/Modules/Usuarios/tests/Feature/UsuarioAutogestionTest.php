<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Resources\UsuarioResource;
use App\Filament\Resources\UsuarioResource\Pages\ListUsuarios;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests funcionales: nadie puede borrar ni modificar su propio usuario desde el backoffice.
 *
 * Caso real (2026-09-25): admin@vida.local se borró a sí mismo desde la tabla de
 * usuarios de Filament y perdió el acceso. Un administrador que se edita a sí
 * mismo podría además quitarse o darse roles sin supervisión.
 */
class UsuarioAutogestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    /**
     * Crea un usuario con el rol indicado.
     *
     * @param string $rol Rol Spatie.
     */
    private function usuarioCon(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    }

    #[Test]
    public function adm_sistema_no_ve_la_accion_de_borrar_sobre_su_propio_usuario(): void
    {
        $admin = $this->usuarioCon('adm_sistema');
        $otro = $this->usuarioCon('consulta_basica');

        Livewire::actingAs($admin)
            ->test(ListUsuarios::class)
            ->assertTableActionHidden('delete', $admin)
            ->assertTableActionHidden('edit', $admin)
            ->assertTableActionVisible('delete', $otro)
            ->assertTableActionVisible('edit', $otro);
    }

    #[Test]
    public function la_autorizacion_de_servidor_deniega_borrar_o_editar_el_propio_usuario(): void
    {
        // Filament resuelve con estas respuestas tanto la visibilidad como la ejecución
        // de las acciones: una petición forjada contra una acción oculta también se deniega.
        $admin = $this->usuarioCon('adm_sistema');
        $this->actingAs($admin);

        $this->assertTrue(UsuarioResource::getDeleteAuthorizationResponse($admin)->denied());
        $this->assertTrue(UsuarioResource::getEditAuthorizationResponse($admin)->denied());
        $this->assertFalse(UsuarioResource::canDelete($admin));
        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    #[Test]
    public function adm_sistema_sigue_pudiendo_borrar_a_otro_usuario(): void
    {
        $admin = $this->usuarioCon('adm_sistema');
        $otro = $this->usuarioCon('consulta_basica');

        Livewire::actingAs($admin)
            ->test(ListUsuarios::class)
            ->callTableAction('delete', $otro);

        $this->assertSoftDeleted('users', ['id' => $otro->id]);
    }

    #[Test]
    public function adm_sistema_no_puede_abrir_la_edicion_de_su_propio_usuario(): void
    {
        $admin = $this->usuarioCon('adm_sistema');
        $otro = $this->usuarioCon('consulta_basica');

        $this->actingAs($admin)
            ->get(UsuarioResource::getUrl('edit', ['record' => $admin]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(UsuarioResource::getUrl('edit', ['record' => $otro]))
            ->assertOk();
    }

    #[Test]
    public function adm_usuarios_no_puede_editar_su_propio_usuario_aunque_este_en_su_uo(): void
    {
        $uo = UnidadOrganizativa::create(['nombre' => 'CSS Prueba', 'tipo' => 'centro', 'activa' => true]);
        $gestora = $this->usuarioCon('adm_usuarios');
        $companera = $this->usuarioCon('intervencion');

        foreach ([$gestora, $companera] as $usuario) {
            UsuarioUo::create([
                'usuario_id' => $usuario->id,
                'unidad_organizativa_id' => $uo->id,
                'tipo_vinculo' => 'interno',
                'fecha_inicio' => today(),
            ]);
        }

        $this->actingAs($gestora);
        $this->assertFalse(UsuarioResource::canEdit($gestora));
        $this->assertTrue(UsuarioResource::canEdit($companera));
    }
}
