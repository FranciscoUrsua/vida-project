<?php

namespace Tests\Feature;

use App\Filament\Concerns\AutorizaGestion;
use App\Filament\Concerns\AutorizaGestionUsuarios;
use App\Filament\Resources\CentroResource;
use App\Filament\Resources\DocumentoResource;
use App\Filament\Resources\EstiloInformeResource;
use App\Filament\Resources\InformeResource;
use App\Filament\Resources\PlantillaInformeResource;
use App\Filament\Resources\ProfesionalResource;
use App\Filament\Resources\TipoCentroResource;
use App\Filament\Resources\TipoSlotResource;
use App\Filament\Resources\UsuarioResource;
use App\Filament\Resources\UsuarioRolResource;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 *   F — Configuración solo para adm_sistema; adm_usuarios, solo gestión de usuarios (25 nov 2026)
 *   G — Enlace de vuelta a la superficie operativa
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

        $this->sinRol = User::factory()->create();
        $this->admSistema = User::factory()->create();
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

    // -------------------------------------------------------------------------
    // Grupo E — ListRecords authorizeAccess: acceso directo por URL
    // -------------------------------------------------------------------------

    #[Test]
    public function supervision_no_puede_acceder_directamente_por_url_a_recurso_restringido(): void
    {
        // Verifica que ListRecords::authorizeAccess() bloquea aunque el nav esté oculto
        $response = $this->actingAs($this->supervision)->get('/admin/cargos');

        $response->assertForbidden();
    }

    #[Test]
    public function adm_sistema_puede_acceder_directamente_por_url_a_cualquier_recurso(): void
    {
        $response = $this->actingAs($this->admSistema)->get('/admin/cargos');

        $response->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Grupo F — Configuración solo para adm_sistema (25 nov 2026)
    // -------------------------------------------------------------------------

    /**
     * Ningún recurso de configuración (trait AutorizaGestion) se abre a adm_usuarios
     * ni a supervision: antes el trait admitía adm_usuarios y le daba casi todo el panel.
     */
    #[Test]
    public function la_configuracion_es_solo_de_adm_sistema(): void
    {
        $recursos = $this->recursosConTrait(AutorizaGestion::class);
        $this->assertNotEmpty($recursos);

        foreach ([$this->admUsuarios, $this->supervision] as $usuario) {
            $this->actingAs($usuario);
            foreach ($recursos as $recurso) {
                // Los que sobrescriben canViewAny() abren la consulta a propósito (centros,
                // redes, plantillas…); los que sobrescriben canCreate() (plantillas y estilos
                // de informe, que la supervisión crea en su ámbito) los cubre el test de altas.
                if (! $this->sobrescribe($recurso, 'canViewAny')) {
                    $this->assertFalse($recurso::canViewAny(), "{$recurso}::canViewAny() para ".$usuario->getRoleNames()->first());
                }
                if (! $this->sobrescribe($recurso, 'canCreate')) {
                    $this->assertFalse($recurso::canCreate(), "{$recurso}::canCreate() para ".$usuario->getRoleNames()->first());
                }
            }
        }

        $this->actingAs($this->admSistema);
        foreach ($recursos as $recurso) {
            $this->assertTrue($recurso::canViewAny(), "{$recurso}::canViewAny() para adm_sistema");
        }
    }

    /**
     * Por URL: adm_usuarios no entra en un catálogo de configuración, pero sí en usuarios.
     */
    #[Test]
    public function adm_usuarios_no_entra_por_url_en_la_configuracion(): void
    {
        $this->actingAs($this->admUsuarios)->get('/admin/cargos')->assertForbidden();
        $this->actingAs($this->admUsuarios)->get('/admin/usuarios')->assertSuccessful();
    }

    /**
     * adm_usuarios conserva la gestión de usuarios: asignaciones de rol, perfiles
     * horarios, excepciones y cuadrantes. supervision no.
     */
    #[Test]
    public function adm_usuarios_conserva_la_gestion_de_usuarios_y_agendas(): void
    {
        $recursos = $this->recursosConTrait(AutorizaGestionUsuarios::class);
        $this->assertCount(4, $recursos);

        foreach ($recursos as $recurso) {
            $this->actingAs($this->admUsuarios);
            $this->assertTrue($recurso::canViewAny(), "{$recurso} para adm_usuarios");
            $this->actingAs($this->supervision);
            $this->assertFalse($recurso::canViewAny(), "{$recurso} para supervision");
        }
    }

    /**
     * adm_usuarios no ve documentos ni informes de ciudadanos en el backoffice, ni
     * edita plantillas de informe ni el catálogo de tipos de slot (este sí la
     * supervisión: T-AGS-02).
     */
    #[Test]
    public function adm_usuarios_no_ve_documentos_ni_edita_plantillas(): void
    {
        $this->actingAs($this->admUsuarios);

        $this->assertFalse(DocumentoResource::canViewAny());
        $this->assertFalse(InformeResource::canViewAny());
        $this->assertFalse(PlantillaInformeResource::canViewAny());
        $this->assertFalse(TipoSlotResource::canCreate());
    }

    /**
     * La migración del 25 nov quita a supervision los permisos que la convertían en
     * administradora de centros y tipos de centro.
     */
    #[Test]
    public function la_migracion_quita_a_supervision_la_gestion_de_centros(): void
    {
        Role::findByName('supervision')->givePermissionTo(['centro.gestionar', 'configuracion.acceder']);
        $this->actingAs($this->supervision->fresh());
        $this->assertTrue(CentroResource::canCreate());

        $migracion = require database_path('migrations/2026_11_25_100001_quita_permisos_de_gestion_a_supervision.php');
        $migracion->up();

        $usuario = User::find($this->supervision->id);
        $this->actingAs($usuario);
        $this->assertFalse($usuario->can('centro.gestionar'));
        $this->assertFalse($usuario->can('configuracion.acceder'));
        $this->assertFalse(CentroResource::canCreate());
        $this->assertFalse(TipoCentroResource::canCreate());
        $this->assertSame(0, DB::table('role_has_permissions')
            ->where('role_id', Role::findByName('supervision')->id)
            ->whereIn('permission_id', DB::table('permissions')->whereIn('name', ['centro.gestionar', 'configuracion.acceder'])->select('id'))
            ->count());
    }

    /**
     * Ningún Resource deja crear a adm_usuarios ni a supervision salvo los de la
     * gestión de usuarios (adm_usuarios), y los tipos de slot (T-AGS-02) y las plantillas
     * y estilos de informe de su ámbito (supervision, 25 nov 2026). Recorre todos: un Resource nuevo sin policy ni canCreate()
     * queda abierto a cualquier usuario del panel, y este test lo detecta.
     */
    #[Test]
    public function solo_la_gestion_de_usuarios_admite_altas_fuera_de_adm_sistema(): void
    {
        // UsuarioRolResource es solo de consulta: nadie crea asignaciones desde ahí.
        $deAdmUsuarios = array_merge(array_diff($this->recursosConTrait(AutorizaGestionUsuarios::class), [
            UsuarioRolResource::class,
        ]), [
            UsuarioResource::class,
            ProfesionalResource::class,
        ]);

        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $fichero) {
            $recurso = 'App\\Filament\\Resources\\'.basename($fichero, '.php');

            $this->actingAs($this->supervision);
            $deSupervision = [TipoSlotResource::class, PlantillaInformeResource::class, EstiloInformeResource::class];
            $this->assertSame(in_array($recurso, $deSupervision, true), $recurso::canCreate(), "{$recurso}::canCreate() para supervision");

            $this->actingAs($this->admUsuarios);
            $this->assertSame(in_array($recurso, $deAdmUsuarios, true), $recurso::canCreate(), "{$recurso}::canCreate() para adm_usuarios");
        }
    }

    // -------------------------------------------------------------------------
    // Grupo G — Enlace de vuelta a la superficie operativa
    // -------------------------------------------------------------------------

    /**
     * Quien tiene superficie operativa ve «Volver a VIDA» en el menú de usuario del panel.
     */
    #[Test]
    public function supervision_ve_el_enlace_de_vuelta_a_la_operativa(): void
    {
        $this->actingAs($this->supervision)->get('/admin')
            ->assertSuccessful()
            ->assertSee('Volver a VIDA');
    }

    /**
     * Quien solo tiene administración no ve el enlace: no tiene adónde volver.
     */
    #[Test]
    public function adm_sistema_solo_no_ve_el_enlace_de_vuelta(): void
    {
        $this->actingAs($this->admSistema)->get('/admin')
            ->assertSuccessful()
            ->assertDontSee('Volver a VIDA');
    }

    /**
     * Si el Resource declara su propio método en lugar de heredarlo del trait.
     *
     * @param class-string $recurso
     * @param string $metodo
     *
     * @return bool
     */
    private function sobrescribe(string $recurso, string $metodo): bool
    {
        $declarado = (new \ReflectionMethod($recurso, $metodo))->getFileName();

        return $declarado === (new \ReflectionClass($recurso))->getFileName();
    }

    /**
     * Resources del panel que usan el trait dado.
     *
     * @param class-string $trait
     *
     * @return list<class-string>
     */
    private function recursosConTrait(string $trait): array
    {
        $recursos = [];
        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $fichero) {
            $clase = 'App\\Filament\\Resources\\'.basename($fichero, '.php');
            if (in_array($trait, class_uses($clase), true)) {
                $recursos[] = $clase;
            }
        }

        return $recursos;
    }
}
