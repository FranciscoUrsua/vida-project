<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Resources\CargoResource\Pages\CreateCargo;
use App\Filament\Resources\CargoResource\Pages\EditCargo;
use App\Filament\Resources\UsuarioResource\Pages\CreateUsuario;
use App\Filament\Resources\UsuarioResource\Pages\EditUsuario;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\RolesSugeridosCargoSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Mensajes\Models\Alerta;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\CargoRolSugerido;
use Modules\Usuarios\Models\ConfiguracionRol;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Modules\Usuarios\Models\UsuarioRol;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests funcionales de roles sugeridos por cargo (TF-USU-RS-01 a TF-USU-RS-06).
 *
 * Las sugerencias solo pre-rellenan el selector de roles del alta de usuario y
 * alimentan un aviso cuando cambia el cargo; nunca otorgan ni retiran roles.
 *
 * @see docs/instrucciones-cli/2026-09-roles-sugeridos-cargo.md
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
class RolesSugeridosCargoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cargo $direccion;

    private Cargo $trabajoSocial;

    private TipoRelacionProfesional $tipoRelacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('adm_sistema');

        $this->direccion = Cargo::create(['nombre' => 'Coordinador/a de Centro', 'slug' => 'coordinador', 'activo' => true]);
        $this->direccion->sincronizarRolesSugeridos(['supervision', 'intervencion']);

        $this->trabajoSocial = Cargo::create(['nombre' => 'Trabajador/a Social', 'slug' => 'ts', 'activo' => true]);
        $this->trabajoSocial->sincronizarRolesSugeridos(['intervencion']);

        $this->tipoRelacion = TipoRelacionProfesional::create([
            'nombre' => 'Funcionario/a de carrera',
            'es_externo' => false,
            'activo' => true,
        ]);
    }

    /**
     * Crea un profesional activo con el cargo indicado.
     *
     * @param Cargo $cargo Cargo del profesional.
     */
    private function profesional(Cargo $cargo, string $nombre = 'Lucía'): Profesional
    {
        return Profesional::create([
            'nombre' => $nombre,
            'apellido1' => 'Prueba',
            'sexo' => 'F',
            'cargo_id' => $cargo->id,
            'tipo_relacion_id' => $this->tipoRelacion->id,
            'fecha_inicio' => today(),
            'activo' => true,
        ]);
    }

    /**
     * Ids de los roles indicados, en el formato del selector del formulario.
     *
     * @param list<string> $nombres
     *
     * @return list<int>
     */
    private function idsRoles(array $nombres): array
    {
        return Role::whereIn('name', $nombres)->orderBy('id')->pluck('id')->all();
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-01 — Pre-relleno en el alta
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_01_elegir_un_profesional_de_direccion_prerrellena_supervision_e_intervencion(): void
    {
        // Dado un profesional con cargo de dirección
        $profesional = $this->profesional($this->direccion);

        // Cuando adm elige el profesional en el alta de usuario
        $componente = Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id]);

        // Entonces el selector de roles contiene exactamente los sugeridos
        $roles = $componente->get('data.roles');
        sort($roles);
        $this->assertSame($this->idsRoles(['supervision', 'intervencion']), array_map('intval', $roles));
    }

    #[Test]
    public function tf_usu_rs_01b_sin_sugerencias_el_selector_queda_vacio(): void
    {
        // profesionales.cargo_id es obligatorio: el caso «sin cargo» no puede darse en BD
        $sinSugerencias = Cargo::create(['nombre' => 'Abogado/a', 'activo' => true]);
        $abogada = $this->profesional($sinSugerencias);
        $directora = $this->profesional($this->direccion, 'Marta');

        // Cambiar de profesional sustituye la sugerencia anterior, no la acumula
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $directora->id])
            ->fillForm(['profesional_id' => $abogada->id])
            ->assertSet('data.roles', []);
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-02 — adm puede quitar roles sugeridos
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_02_un_rol_sugerido_quitado_antes_de_guardar_no_se_asigna(): void
    {
        $profesional = $this->profesional($this->direccion);

        // Cuando adm quita «supervision» de los roles pre-rellenados y guarda
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm([
                'email' => 'directora@vida360.test',
                'password' => 'secreto123',
                'roles' => $this->idsRoles(['intervencion']),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Entonces el usuario se crea solo con intervencion, ni efectivo ni pendiente de supervision
        $usuario = User::where('email', 'directora@vida360.test')->firstOrFail();
        $this->assertTrue($usuario->hasRole('intervencion'));
        $this->assertFalse($usuario->hasRole('supervision'));
        $this->assertFalse($usuario->hasRole('consulta_basica'));
        $this->assertDatabaseMissing('usuario_rol', [
            'usuario_id' => $usuario->id,
            'rol_id' => Role::findByName('supervision')->id,
        ]);
        // El rol asignado deja historial en usuario_rol con quién lo asignó
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $usuario->id,
            'rol_id' => Role::findByName('intervencion')->id,
            'estado' => 'activo',
            'asignado_por' => $this->admin->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-03 — Los roles pre-rellenados siguen el flujo normal
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_03_supervision_prerrellenado_queda_pendiente_de_aprobacion_hasta_que_se_aprueba(): void
    {
        ConfiguracionRol::create([
            'rol_id' => Role::findByName('supervision')->id,
            'nivel_supervision' => 'aprobacion_previa',
        ]);
        ConfiguracionRol::create([
            'rol_id' => Role::findByName('intervencion')->id,
            'nivel_supervision' => 'alerta_supervisada',
        ]);
        $profesional = $this->profesional($this->direccion);
        $raiz = UnidadOrganizativa::create(['nombre' => 'Ayuntamiento', 'tipo' => 'ayuntamiento', 'activa' => true]);
        $ciam = UnidadOrganizativa::create(['nombre' => 'CIAM', 'tipo' => 'centro', 'parent_id' => $raiz->id, 'activa' => true]);

        // Cuando adm guarda el alta, adscrita al CIAM, con los roles sugeridos sin tocarlos
        $deshacerFake = Repeater::fake();
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm([
                'email' => 'directora@vida360.test',
                'password' => 'secreto123',
                'adscripciones' => [[
                    'unidad_organizativa_id' => $ciam->id,
                    'tipo_vinculo' => 'interno',
                    'fecha_inicio' => today()->toDateString(),
                    'fecha_fin' => null,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $deshacerFake();

        $usuario = User::where('email', 'directora@vida360.test')->firstOrFail();

        // Entonces supervision queda pendiente y no es efectivo
        $solicitud = UsuarioRol::where('usuario_id', $usuario->id)
            ->where('rol_id', Role::findByName('supervision')->id)
            ->firstOrFail();
        $this->assertSame('pendiente_aprobacion', $solicitud->estado);
        $this->assertFalse($usuario->fresh()->hasRole('supervision'));

        // e intervencion es efectivo de inmediato con alerta a la supervisión de su UO
        $this->assertTrue($usuario->fresh()->hasRole('intervencion'));
        $alertas = Alerta::where('origen_type', UsuarioRol::class)->get();
        $this->assertCount(1, $alertas, 'Solo el rol de alerta supervisada genera alerta.');
        $this->assertSame('supervision', $alertas->first()->destinatario_rol);
        $this->assertSame($ciam->id, $alertas->first()->destinatario_uo_id);

        // Cuando se aprueba, supervision pasa a ser efectivo
        $solicitud->update(['estado' => 'activo']);
        $this->assertTrue($usuario->fresh()->hasRole('supervision'));
    }

    #[Test]
    public function tf_usu_rs_03b_sin_configuracion_explicita_supervision_sigue_requiriendo_aprobacion(): void
    {
        // Negativo: sin fila en configuracion_roles no puede activarse supervision sin aprobación
        $profesional = $this->profesional($this->direccion);

        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm(['email' => 'directora@vida360.test', 'password' => 'secreto123'])
            ->call('create')
            ->assertHasNoFormErrors();

        $usuario = User::where('email', 'directora@vida360.test')->firstOrFail();
        $this->assertFalse($usuario->hasRole('supervision'));
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $usuario->id,
            'rol_id' => Role::findByName('supervision')->id,
            'estado' => 'pendiente_aprobacion',
        ]);
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-04 — Cambio de cargo: aviso, sin tocar roles
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_04_cambiar_el_cargo_no_modifica_los_roles_y_muestra_el_aviso(): void
    {
        // Dado un usuario de trabajo social con rol intervencion
        $profesional = $this->profesional($this->trabajoSocial);
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm(['email' => 'ts@vida360.test', 'password' => 'secreto123'])
            ->call('create')
            ->assertHasNoFormErrors();
        $usuario = User::where('email', 'ts@vida360.test')->firstOrFail();

        // Sin cambio de cargo no hay aviso
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertDontSee('El cargo ha cambiado');

        // Cuando su profesional pasa a dirección
        $profesional->update(['cargo_id' => $this->direccion->id]);

        // Entonces sus roles no cambian
        $usuario = $usuario->fresh();
        $this->assertSame(['intervencion'], $usuario->getRoleNames()->all());
        $this->assertFalse(UsuarioRol::where('usuario_id', $usuario->id)
            ->where('rol_id', Role::findByName('supervision')->id)
            ->exists());

        // y la ficha muestra el aviso con los roles sugeridos del nuevo cargo
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertSee('El cargo ha cambiado')
            ->assertSee('supervision')
            ->callAction('descartarAvisoCargo');

        // Descartado, el aviso desaparece
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertDontSee('El cargo ha cambiado');
    }

    #[Test]
    public function tf_usu_rs_04b_editar_los_roles_del_usuario_retira_el_aviso(): void
    {
        $profesional = $this->profesional($this->trabajoSocial);
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm(['email' => 'ts@vida360.test', 'password' => 'secreto123'])
            ->call('create');
        $usuario = User::where('email', 'ts@vida360.test')->firstOrFail();
        $profesional->update(['cargo_id' => $this->direccion->id]);

        // Guardar sin tocar roles no retira el aviso
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertSee('El cargo ha cambiado');

        // Editar los roles sí lo retira
        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->fillForm(['roles' => $this->idsRoles(['intervencion', 'tramitacion'])])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::actingAs($this->admin)
            ->test(EditUsuario::class, ['record' => $usuario->getRouteKey()])
            ->assertDontSee('El cargo ha cambiado');
        $this->assertTrue($usuario->fresh()->hasRole('tramitacion'));
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-05 — Cambiar sugerencias no toca usuarios existentes
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_05_cambiar_las_sugerencias_de_un_cargo_no_altera_roles_de_usuarios(): void
    {
        $profesional = $this->profesional($this->trabajoSocial);
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->fillForm(['profesional_id' => $profesional->id])
            ->fillForm(['email' => 'ts@vida360.test', 'password' => 'secreto123'])
            ->call('create');
        $usuario = User::where('email', 'ts@vida360.test')->firstOrFail();
        $historialAntes = UsuarioRol::where('usuario_id', $usuario->id)->count();

        // Cuando adm cambia las sugerencias del cargo desde Filament
        Livewire::actingAs($this->admin)
            ->test(EditCargo::class, ['record' => $this->trabajoSocial->getRouteKey()])
            ->assertSet('data.roles_sugeridos', ['intervencion'])
            ->fillForm(['roles_sugeridos' => ['tramitacion', 'consulta_basica']])
            ->call('save')
            ->assertHasNoFormErrors();

        // Entonces las sugerencias cambian pero los roles del usuario no
        $this->assertEqualsCanonicalizing(
            ['tramitacion', 'consulta_basica'],
            $this->trabajoSocial->fresh()->rolesSugeridos()->pluck('rol')->all()
        );
        $usuario = $usuario->fresh();
        $this->assertSame(['intervencion'], $usuario->getRoleNames()->all());
        $this->assertSame($historialAntes, UsuarioRol::where('usuario_id', $usuario->id)->count());
    }

    // -------------------------------------------------------------------------
    // TF-USU-RS-06 — Negativo: rol inexistente
    // -------------------------------------------------------------------------

    #[Test]
    public function tf_usu_rs_06_no_se_puede_guardar_como_sugerido_un_rol_inexistente(): void
    {
        try {
            CargoRolSugerido::create(['cargo_id' => $this->trabajoSocial->id, 'rol' => 'rol_inventado']);
            $this->fail('Se esperaba InvalidArgumentException al sugerir un rol inexistente.');
        } catch (InvalidArgumentException) {
            // esperado
        }

        $this->assertDatabaseMissing('cargo_roles_sugeridos', ['rol' => 'rol_inventado']);
    }

    #[Test]
    public function tf_usu_rs_06b_el_formulario_de_cargos_rechaza_un_rol_inexistente(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateCargo::class)
            ->fillForm([
                'nombre' => 'Cargo nuevo',
                'slug' => 'cargo-nuevo',
                'roles_sugeridos' => ['rol_inventado'],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles_sugeridos']);

        $this->assertDatabaseMissing('cargo_roles_sugeridos', ['rol' => 'rol_inventado']);
    }

    // -------------------------------------------------------------------------
    // Fase 3 — Seeder de sugerencias iniciales
    // -------------------------------------------------------------------------

    #[Test]
    public function el_seeder_es_idempotente_no_sobrescribe_y_no_crea_cargos(): void
    {
        // Dado un cargo ya configurado a mano y otro sin sugerencias
        $this->direccion->sincronizarRolesSugeridos(['adm_usuarios']);
        $administrativo = Cargo::create(['nombre' => 'Administrativo/a', 'activo' => true]);
        $cargosAntes = Cargo::count();

        // Cuando el seeder se ejecuta dos veces
        $this->seed(RolesSugeridosCargoSeeder::class);
        $this->seed(RolesSugeridosCargoSeeder::class);

        // Entonces no sobrescribe lo configurado, completa lo vacío y no crea cargos
        $this->assertSame(['adm_usuarios'], $this->direccion->rolesSugeridos()->pluck('rol')->all());
        $this->assertSame(['tramitacion'], $administrativo->rolesSugeridos()->pluck('rol')->all());
        $this->assertSame($cargosAntes, Cargo::count());
        $this->assertSame(1, $administrativo->rolesSugeridos()->count());
    }
}
