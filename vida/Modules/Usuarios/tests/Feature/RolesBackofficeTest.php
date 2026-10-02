<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Resources\RolResource\Pages\CreateRol;
use App\Filament\Resources\RolResource\Pages\EditRol;
use App\Filament\Resources\RolResource\Pages\ListRoles;
use App\Models\User;
use Database\Seeders\ConfiguracionRolesSeeder;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Usuarios\Models\ConfiguracionRol;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Backoffice de roles: una sola pantalla (Filament «Roles y permisos») con los
 * permisos del rol y el nivel de supervisión que exige su asignación
 * (docs/modulo-usuarios-permisos.md §2.8 y §4.5). Solo adm_sistema.
 *
 * Incluye la retirada del catálogo de zonas, sustituido por las secciones censales.
 */
class RolesBackofficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(ConfiguracionRolesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('adm_sistema');
    }

    #[Test]
    public function el_listado_de_roles_muestra_el_nivel_de_supervision(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListRoles::class)
            ->assertSee('Aprobación previa')
            ->assertSee('Alerta supervisada');
    }

    #[Test]
    public function editar_un_rol_carga_su_nivel_de_supervision(): void
    {
        $supervision = Role::findByName('supervision', 'web');

        Livewire::actingAs($this->admin)
            ->test(EditRol::class, ['record' => $supervision->getRouteKey()])
            ->assertSet('data.nivel_supervision', ConfiguracionRol::APROBACION_PREVIA);
    }

    #[Test]
    public function guardar_el_rol_cambia_su_nivel_y_sus_permisos_en_la_misma_pantalla(): void
    {
        $tramitacion = Role::findByName('tramitacion', 'web');
        $permiso = Permission::where('name', 'historia.leer')->firstOrFail();
        $permisos = $tramitacion->permissions->pluck('id')->push($permiso->id)->map(fn ($id) => (string) $id)->all();

        Livewire::actingAs($this->admin)
            ->test(EditRol::class, ['record' => $tramitacion->getRouteKey()])
            ->set('data.nivel_supervision', ConfiguracionRol::APROBACION_PREVIA)
            ->set('data.permissions', $permisos)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, ConfiguracionRol::where('rol_id', $tramitacion->id)->count());
        $this->assertSame(ConfiguracionRol::APROBACION_PREVIA, ConfiguracionRol::nivelPara($tramitacion));
        $this->assertTrue($tramitacion->fresh()->hasPermissionTo('historia.leer'));
    }

    #[Test]
    public function crear_un_rol_guarda_su_nivel_de_supervision(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRol::class)
            ->set('data.name', 'auditoria_externa')
            ->set('data.nivel_supervision', ConfiguracionRol::APROBACION_PREVIA)
            ->call('create')
            ->assertHasNoFormErrors();

        $rol = Role::findByName('auditoria_externa', 'web');
        $this->assertSame(ConfiguracionRol::APROBACION_PREVIA, ConfiguracionRol::where('rol_id', $rol->id)->value('nivel_supervision'));
    }

    #[Test]
    public function el_nivel_de_supervision_es_obligatorio(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRol::class)
            ->set('data.name', 'sin_nivel')
            ->set('data.nivel_supervision', null)
            ->call('create')
            ->assertHasFormErrors(['nivel_supervision' => 'required']);

        $this->assertNull(Role::where('name', 'sin_nivel')->first());
    }

    #[Test]
    public function adm_usuarios_no_entra_en_la_pantalla_de_roles(): void
    {
        $gestor = User::factory()->create();
        $gestor->assignRole('adm_usuarios');

        $this->actingAs($gestor)->get('/admin/rols')->assertForbidden();
        $this->actingAs($gestor)->get('/admin/rols/'.Role::findByName('intervencion', 'web')->id.'/edit')->assertForbidden();
    }

    #[Test]
    public function la_antigua_pantalla_de_supervision_de_roles_ya_no_existe(): void
    {
        $this->actingAs($this->admin)->get('/admin/configuracion-rols')->assertNotFound();
    }

    #[Test]
    public function el_catalogo_de_zonas_ya_no_existe(): void
    {
        $this->actingAs($this->admin)->get('/admin/zonas')->assertNotFound();
        $this->assertFalse(Schema::hasTable('zonas'));
    }
}
