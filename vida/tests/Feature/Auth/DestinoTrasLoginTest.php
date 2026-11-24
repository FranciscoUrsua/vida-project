<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del destino tras el login y de la raíz «/» según los roles del usuario.
 *
 * La superficie operativa de supervisión tiene prioridad sobre el backoffice para
 * quien combina supervisión con administración de usuarios (p. ej. la dirección de
 * un centro); solo adm_sistema entra siempre directamente al panel Filament.
 */
class DestinoTrasLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Siembra permisos y roles del sistema.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    /**
     * Dada una usuaria con supervision + adm_usuarios + intervencion (dirección de centro);
     * cuando inicia sesión o visita «/»; entonces va a la supervisión operativa, no a /admin.
     */
    #[Test]
    public function supervision_con_adm_usuarios_entra_en_la_supervision_operativa(): void
    {
        $directora = $this->usuarioConRoles(['intervencion', 'supervision', 'adm_usuarios'], 'dir@test.es');

        $this->post('/login', ['email' => 'dir@test.es', 'password' => 'secreto123'])
            ->assertRedirect(route('supervision.inicio'));

        $this->actingAs($directora)->get('/')->assertRedirect(route('supervision.inicio'));
    }

    /**
     * Dada una usuaria solo con adm_usuarios; cuando inicia sesión o visita «/»; entonces va a /admin.
     */
    #[Test]
    public function adm_usuarios_sin_supervision_entra_en_el_backoffice(): void
    {
        $adm = $this->usuarioConRoles(['adm_usuarios'], 'adm@test.es');

        $this->post('/login', ['email' => 'adm@test.es', 'password' => 'secreto123'])
            ->assertRedirect('/admin');

        $this->actingAs($adm)->get('/')->assertRedirect('/admin');
    }

    /**
     * Las superficies operativas van antes que la administración (R4.5, TF-STG-25):
     * `adm_sistema` con `supervision` entra en Supervisión; `adm_sistema` solo, en el backoffice.
     */
    #[Test]
    public function adm_sistema_con_supervision_entra_en_supervision_y_solo_en_el_backoffice(): void
    {
        $conSupervision = $this->usuarioConRoles(['adm_sistema', 'supervision'], 'sis@test.es');
        $this->actingAs($conSupervision)->get('/')->assertRedirect(route('supervision.inicio'));

        $solo = $this->usuarioConRoles(['adm_sistema'], 'sis.solo@test.es');
        $this->actingAs($solo)->get('/')->assertRedirect('/admin');
    }

    /**
     * Crea un usuario activo con los roles indicados y contraseña «secreto123».
     *
     * @param list<string> $roles Roles de Spatie
     * @param string $email Correo del usuario
     */
    private function usuarioConRoles(array $roles, string $email): User
    {
        $usuario = User::factory()->create([
            'email' => $email,
            'password' => 'secreto123',
            'email_verified_at' => now(),
            'primer_acceso' => false,
        ]);
        $usuario->syncRoles($roles);

        return $usuario;
    }
}
