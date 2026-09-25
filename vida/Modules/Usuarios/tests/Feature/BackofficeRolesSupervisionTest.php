<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Filament\Resources\UsuarioResource\Pages\CreateUsuario;
use App\Filament\Resources\UsuarioRolResource;
use App\Filament\Resources\UsuarioRolResource\Pages\ListUsuarioRoles;
use App\Models\User;
use Database\Seeders\ConfiguracionRolesSeeder;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\ConfiguracionRol;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use Modules\Usuarios\Models\UsuarioRol;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests del backoffice de usuarios: alta rápida de profesional, historial de roles
 * de solo lectura y configuración explícita del nivel de supervisión de cada rol.
 *
 * @see docs/modulo-usuarios-permisos.md secciones 2.8 y 2.9
 */
class BackofficeRolesSupervisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cargo $direccion;

    private TipoRelacionProfesional $tipoRelacion;

    /**
     * Prepara roles, un administrador y los catálogos mínimos para dar de alta profesionales.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('adm_sistema');

        $this->direccion = Cargo::create(['nombre' => 'Coordinador/a de Centro', 'slug' => 'coordinador', 'activo' => true]);
        $this->direccion->sincronizarRolesSugeridos(['supervision', 'intervencion']);

        $this->tipoRelacion = TipoRelacionProfesional::create([
            'nombre' => 'Funcionario/a de carrera',
            'es_externo' => false,
            'activo' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Alta rápida de profesional desde el alta de usuario
    // -------------------------------------------------------------------------

    #[Test]
    public function crear_profesional_desde_el_alta_de_usuario_lo_selecciona_y_prerrellena_roles(): void
    {
        // Cuando adm crea el profesional desde el propio selector del alta de usuario
        $componente = Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->callFormComponentAction('profesional_id', 'createOption', data: [
                'nombre' => 'Marta',
                'apellido1' => 'Nueva',
                'sexo' => 'F',
                'cargo_id' => $this->direccion->id,
                'tipo_relacion_id' => $this->tipoRelacion->id,
                'fecha_inicio' => today()->toDateString(),
                'activo' => true,
            ])
            ->assertHasNoFormComponentActionErrors();

        // Entonces el profesional existe, queda seleccionado y los roles sugeridos de su cargo se pre-rellenan
        $profesional = Profesional::where('apellido1', 'Nueva')->firstOrFail();
        $this->assertSame($profesional->id, (int) $componente->get('data.profesional_id'));

        $roles = array_map('intval', $componente->get('data.roles'));
        sort($roles);
        $this->assertSame(
            Role::whereIn('name', ['supervision', 'intervencion'])->orderBy('id')->pluck('id')->all(),
            $roles
        );
    }

    #[Test]
    public function el_alta_rapida_de_profesional_valida_los_campos_obligatorios(): void
    {
        // Cuando falta el cargo, que es obligatorio en la ficha de profesional
        Livewire::actingAs($this->admin)
            ->test(CreateUsuario::class)
            ->callFormComponentAction('profesional_id', 'createOption', data: [
                'nombre' => 'Sin',
                'apellido1' => 'Cargo',
                'sexo' => 'F',
                'tipo_relacion_id' => $this->tipoRelacion->id,
                'fecha_inicio' => today()->toDateString(),
            ])
            ->assertHasFormComponentActionErrors(['cargo_id']);

        // Entonces no se crea ningún profesional
        $this->assertSame(0, Profesional::where('apellido1', 'Cargo')->count());
    }

    // -------------------------------------------------------------------------
    // Historial de roles de solo lectura
    // -------------------------------------------------------------------------

    #[Test]
    public function el_historial_de_roles_se_consulta_pero_no_admite_altas_ediciones_ni_borrados(): void
    {
        $asignacion = UsuarioRol::create([
            'usuario_id' => $this->admin->id,
            'rol_id' => Role::findByName('intervencion')->id,
            'fecha_inicio' => today(),
            'estado' => 'activo',
        ]);

        // El listado sigue disponible y muestra la asignación
        Livewire::actingAs($this->admin)
            ->test(ListUsuarioRoles::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$asignacion])
            ->assertActionDoesNotExist('create');

        // Pero no hay páginas de alta ni de edición, ni permisos para crear, editar o borrar
        $this->assertSame(['index'], array_keys(UsuarioRolResource::getPages()));
        $this->actingAs($this->admin);
        $this->assertFalse(UsuarioRolResource::canCreate());
        $this->assertFalse(UsuarioRolResource::canEdit($asignacion));
        $this->assertFalse(UsuarioRolResource::canDelete($asignacion));
    }

    // -------------------------------------------------------------------------
    // Nivel de supervisión explícito por rol
    // -------------------------------------------------------------------------

    #[Test]
    public function el_seeder_configura_todos_los_roles_con_el_nivel_de_2_8(): void
    {
        $this->seed(ConfiguracionRolesSeeder::class);

        $niveles = ConfiguracionRol::with('rol')->get()->mapWithKeys(fn (ConfiguracionRol $c) => [$c->rol->name => $c->nivel_supervision]);

        $this->assertSame(Role::count(), $niveles->count());
        $this->assertSame(ConfiguracionRol::APROBACION_PREVIA, $niveles['adm_sistema']);
        $this->assertSame(ConfiguracionRol::APROBACION_PREVIA, $niveles['supervision']);
        foreach (['adm_usuarios', 'intervencion', 'tramitacion', 'consulta_profesional', 'consulta_basica'] as $rol) {
            $this->assertSame(ConfiguracionRol::ALERTA_SUPERVISADA, $niveles[$rol], $rol);
        }
    }

    #[Test]
    public function el_seeder_no_sobrescribe_niveles_ya_configurados_y_es_idempotente(): void
    {
        // Dado un rol configurado a mano con un nivel distinto del de por defecto
        ConfiguracionRol::create([
            'rol_id' => Role::findByName('intervencion')->id,
            'nivel_supervision' => ConfiguracionRol::APROBACION_PREVIA,
        ]);

        $this->seed(ConfiguracionRolesSeeder::class);
        $this->seed(ConfiguracionRolesSeeder::class);

        $this->assertSame(Role::count(), ConfiguracionRol::count());
        $this->assertSame(
            ConfiguracionRol::APROBACION_PREVIA,
            ConfiguracionRol::nivelPara(Role::findByName('intervencion'))
        );
    }
}
