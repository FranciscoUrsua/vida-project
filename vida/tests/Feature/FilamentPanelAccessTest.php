<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de control de acceso al panel Filament.
 *
 * Verifica que canAccessPanel y los métodos can* de cada Resource
 * aplican correctamente las restricciones de rol definidas en la
 * tarea de autorización del panel (sección 4.5 del spec de seguridad).
 *
 * Grupos:
 *   A — Acceso al panel (TF-AUTH-01 a TF-AUTH-05)
 *   B — RolResource: solo adm_sistema (TF-AUTH-06 a TF-AUTH-08)
 *   C — UsuarioResource: adm_sistema + adm_usuarios (TF-AUTH-09 a TF-AUTH-11)
 *   D — LogAlertasResource: adm_sistema + supervision (TF-AUTH-12 a TF-AUTH-14)
 */
class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $sinRol;
    private User $admSistema;
    private User $supervision;
    private User $admUsuarios;
    private User $intervencion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->sinRol      = User::factory()->create();
        $this->admSistema  = User::factory()->create();
        $this->supervision = User::factory()->create();
        $this->admUsuarios = User::factory()->create();
        $this->intervencion = User::factory()->create();

        $this->admSistema->assignRole('adm_sistema');
        $this->supervision->assignRole('supervision');
        $this->admUsuarios->assignRole('adm_usuarios');
        $this->intervencion->assignRole('intervencion');
    }

    // -------------------------------------------------------------------------
    // Grupo A — Acceso al panel
    // -------------------------------------------------------------------------

    #[Test]
    public function usuario_sin_rol_no_puede_acceder_al_panel(): void
    {
        $response = $this->actingAs($this->sinRol)->get('/admin');

        // El middleware cierra la sesión y redirige al login (no 403)
        $response->assertRedirect('/admin/login');
    }

    #[Test]
    public function adm_sistema_puede_acceder_al_panel(): void
    {
        $response = $this->actingAs($this->admSistema)->get('/admin');

        $response->assertSuccessful();
    }

    #[Test]
    public function supervision_puede_acceder_al_panel(): void
    {
        $response = $this->actingAs($this->supervision)->get('/admin');

        $response->assertSuccessful();
    }

    #[Test]
    public function adm_usuarios_puede_acceder_al_panel(): void
    {
        $response = $this->actingAs($this->admUsuarios)->get('/admin');

        $response->assertSuccessful();
    }

    #[Test]
    public function intervencion_no_puede_acceder_al_panel(): void
    {
        $response = $this->actingAs($this->intervencion)->get('/admin');

        // El middleware cierra la sesión y redirige al login (no 403)
        $response->assertRedirect('/admin/login');
    }

    // -------------------------------------------------------------------------
    // Grupo B — RolResource: solo adm_sistema
    // -------------------------------------------------------------------------

    #[Test]
    public function adm_sistema_puede_listar_rols(): void
    {
        $response = $this->actingAs($this->admSistema)->get('/admin/rols');

        $response->assertSuccessful();
    }

    #[Test]
    public function adm_usuarios_no_puede_acceder_a_rols(): void
    {
        $response = $this->actingAs($this->admUsuarios)->get('/admin/rols');

        $response->assertForbidden();
    }

    #[Test]
    public function supervision_no_puede_acceder_a_rols(): void
    {
        $response = $this->actingAs($this->supervision)->get('/admin/rols');

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Grupo C — UsuarioResource: adm_sistema + adm_usuarios
    // -------------------------------------------------------------------------

    #[Test]
    public function adm_sistema_puede_listar_usuarios(): void
    {
        $response = $this->actingAs($this->admSistema)->get('/admin/usuarios');

        $response->assertSuccessful();
    }

    #[Test]
    public function adm_usuarios_puede_listar_usuarios(): void
    {
        $response = $this->actingAs($this->admUsuarios)->get('/admin/usuarios');

        $response->assertSuccessful();
    }

    #[Test]
    public function supervision_no_puede_listar_usuarios(): void
    {
        $response = $this->actingAs($this->supervision)->get('/admin/usuarios');

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Grupo D — LogAlertasResource: adm_sistema + supervision
    // -------------------------------------------------------------------------

    #[Test]
    public function adm_sistema_puede_ver_log_alertas(): void
    {
        $response = $this->actingAs($this->admSistema)->get('/admin/log-alertas');

        $response->assertSuccessful();
    }

    #[Test]
    public function supervision_puede_ver_log_alertas(): void
    {
        $response = $this->actingAs($this->supervision)->get('/admin/log-alertas');

        $response->assertSuccessful();
    }

    #[Test]
    public function adm_usuarios_no_puede_ver_log_alertas(): void
    {
        $response = $this->actingAs($this->admUsuarios)->get('/admin/log-alertas');

        $response->assertForbidden();
    }
}
