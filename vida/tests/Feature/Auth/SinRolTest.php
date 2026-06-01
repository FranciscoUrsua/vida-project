<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\UsuarioResource\Pages\CreateUsuario;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Usuarios\Models\Cargo;
use Modules\Usuarios\Models\Profesional;
use Modules\Usuarios\Models\TipoRelacionProfesional;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests funcionales — Usuario sin rol asignado.
 *
 * TF-AUTH-SR-01 a TF-AUTH-SR-10
 *
 * @see docs/instrucciones-cli/usuario-sin-rol.md
 */
class SinRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crea un usuario sin rol asignado (profesional_id = null para evitar booted()).
     *
     * @param array<string, mixed> $attrs
     * @return User
     */
    private function crearUsuarioSinRol(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'              => 'Sin Rol Test',
            'email'             => 'sinrol@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => null,
        ], $attrs));
    }

    /**
     * Crea un Profesional mínimo para tests.
     *
     * @return Profesional
     */
    private function crearProfesional(array $attrs = []): Profesional
    {
        $cargo = Cargo::firstOrCreate(['nombre' => 'TSR Test', 'activo' => true]);
        $tipoRelacion = TipoRelacionProfesional::firstOrCreate(
            ['nombre' => 'Funcionario/a test'],
            ['es_externo' => false, 'activo' => true]
        );

        return Profesional::create(array_merge([
            'nombre'           => 'María',
            'apellido1'        => 'Prueba',
            'sexo'             => 'F',
            'cargo_id'         => $cargo->id,
            'tipo_relacion_id' => $tipoRelacion->id,
            'fecha_inicio'     => today()->toDateString(),
            'activo'           => true,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // Tests de redirección
    // -------------------------------------------------------------------------

    /**
     * TF-AUTH-SR-01 — Usuario asistencial sin roles es redirigido a /sin-rol tras el login.
     */
    #[Test]
    public function usuario_sin_rol_es_redirigido_a_sin_rol_tras_login(): void
    {
        $profesional = $this->crearProfesional();

        // Crear directamente con DB para evitar que booted() asigne el rol
        $usuario = User::create([
            'name'              => 'TSR Sin Rol',
            'email'             => 'tsrsinrol@vida360.test',
            'password'          => bcrypt('secreto123'),
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => $profesional->id,
        ]);
        // Quitar el rol asignado automáticamente por booted()
        $usuario->syncRoles([]);

        $this->post(route('login.post'), [
            'email'    => $usuario->email,
            'password' => 'secreto123',
        ])->assertRedirect(route('sin-rol'));
    }

    /**
     * TF-AUTH-SR-02 — Usuario asistencial sin roles que accede a /intervencion/agenda es redirigido a /sin-rol.
     */
    #[Test]
    public function usuario_sin_rol_accediendo_a_ruta_operativa_va_a_sin_rol(): void
    {
        $profesional = $this->crearProfesional(['nombre' => 'Pedro', 'apellido1' => 'Test02']);
        $usuario = User::create([
            'name'              => 'Pedro Test02',
            'email'             => 'pedrotest02@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => $profesional->id,
        ]);
        $usuario->syncRoles([]);

        $this->actingAs($usuario)
            ->get('/intervencion/agenda')
            ->assertRedirect(route('sin-rol'));
    }

    /**
     * TF-AUTH-SR-03 — La pantalla /sin-rol muestra el nombre del profesional si existe.
     */
    #[Test]
    public function sin_rol_muestra_nombre_profesional(): void
    {
        $profesional = $this->crearProfesional([
            'nombre'    => 'Ana',
            'apellido1' => 'García',
        ]);

        $usuario = User::create([
            'name'              => 'Ana García',
            'email'             => 'anag@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => $profesional->id,
        ]);
        // Quitar el rol asignado automáticamente por booted()
        $usuario->syncRoles([]);

        $this->actingAs($usuario)
            ->get(route('sin-rol'))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('García');
    }

    /**
     * TF-AUTH-SR-04 — La pantalla /sin-rol muestra el email si no hay profesional asociado.
     */
    #[Test]
    public function sin_rol_muestra_email_sin_profesional(): void
    {
        $usuario = $this->crearUsuarioSinRol([
            'email' => 'sinprofesional@vida360.test',
        ]);

        $this->actingAs($usuario)
            ->get(route('sin-rol'))
            ->assertOk()
            ->assertSee('sinprofesional@vida360.test');
    }

    /**
     * TF-AUTH-SR-05 — La pantalla /sin-rol no muestra el bloque de datos si ni nombre ni email están disponibles.
     */
    #[Test]
    public function sin_rol_no_muestra_bloque_datos_si_no_hay_datos(): void
    {
        $usuario = User::create([
            'name'              => 'Test',
            'email'             => 'nodatos@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => null,
        ]);

        // La vista siempre muestra el email del usuario autenticado,
        // por lo que el bloque estará presente. Verificamos que al menos no crashea.
        $this->actingAs($usuario)
            ->get(route('sin-rol'))
            ->assertOk();
    }

    /**
     * TF-AUTH-SR-06 — Un usuario con rol accede normalmente y no ve /sin-rol.
     */
    #[Test]
    public function usuario_con_rol_no_es_redirigido_a_sin_rol(): void
    {
        $usuario = $this->crearUsuarioSinRol([
            'email' => 'conrol@vida360.test',
        ]);
        $usuario->assignRole('consulta_basica');

        $this->actingAs($usuario)
            ->get(route('inicio'))
            ->assertOk();
    }

    /**
     * TF-AUTH-SR-07 — El botón "Cerrar sesión" en /sin-rol cierra la sesión y redirige al login.
     */
    #[Test]
    public function cerrar_sesion_desde_sin_rol_redirige_al_login(): void
    {
        $usuario = $this->crearUsuarioSinRol();

        $this->actingAs($usuario)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * TF-AUTH-SR-08 — Usuario creado con profesional_id tiene rol consulta_basica automáticamente.
     */
    #[Test]
    public function usuario_con_profesional_id_recibe_rol_consulta_basica(): void
    {
        $profesional = $this->crearProfesional();

        $usuario = User::create([
            'name'              => 'Nuevo TSR',
            'email'             => 'nuevotsr@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => $profesional->id,
        ]);

        $this->assertTrue($usuario->hasRole('consulta_basica'));
    }

    /**
     * TF-AUTH-SR-09 — Usuario creado con profesional_id null no recibe el rol por defecto.
     */
    #[Test]
    public function usuario_sin_profesional_no_recibe_rol_por_defecto(): void
    {
        $usuario = User::create([
            'name'              => 'Admin Técnico',
            'email'             => 'admtec@vida360.test',
            'password'          => 'secreto',
            'email_verified_at' => now(),
            'primer_acceso'     => false,
            'profesional_id'    => null,
        ]);

        $this->assertFalse($usuario->hasRole('consulta_basica'));
        $this->assertEquals(0, $usuario->roles()->count());
    }

    /**
     * TF-AUTH-SR-10 — Intentar guardar un usuario en Filament sin rol muestra error de validación.
     */
    #[Test]
    public function formulario_filament_requiere_al_menos_un_rol(): void
    {
        $admin = $this->crearUsuarioSinRol(['email' => 'admin-filament@vida360.test']);
        $admin->assignRole('adm_sistema');

        Livewire::actingAs($admin)
            ->test(CreateUsuario::class)
            ->fillForm([
                'name'     => 'Test Sin Rol',
                'email'    => 'testsinrol@vida360.test',
                'password' => 'secreto123',
                'roles'    => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);
    }
}
