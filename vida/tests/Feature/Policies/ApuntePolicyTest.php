<?php

namespace Tests\Feature\Policies;

use App\Models\Apunte;
use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de la ApuntePolicy.
 *
 * Verifica la regla especial de anotaciones privadas
 * (docs/modulo-usuarios-permisos.md § 4.7) y la restricción
 * del rol tramitacion (caso 3 de la especificación de tests):
 *
 *   - Un usuario con rol tramitacion no puede crear apuntes.
 *   - Nadie puede leer una anotación privada ajena, sin importar el rol.
 */
class ApuntePolicyTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    /**
     * Prepara roles y UO necesarios para los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermisosSeeder::class);
        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre'    => 'CSS Pruebas',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Caso 3: tramitacion no puede crear apuntes
    // -------------------------------------------------------------------------

    /**
     * Un usuario con rol tramitacion no tiene el permiso apunte.crear
     * y por tanto no puede crear apuntes en una Historia Social.
     *
     * El rol tramitacion gestiona trámites administrativos, pero no
     * accede a contenido clínico/social (docs/modulo-usuarios-permisos.md ROL 5).
     */
    #[Test]
    public function tramitacion_no_puede_crear_apuntes(): void
    {
        $usuario = $this->crearProfesionalEnUo('tramitacion', $this->uo);

        $this->assertFalse(
            $usuario->can('create', Apunte::class),
            'El rol tramitacion no debe tener permiso para crear apuntes.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 4: Nadie puede leer anotación privada que no sea suya
    // -------------------------------------------------------------------------

    /**
     * Un usuario con rol adm_sistema (máximo privilegio) no puede leer
     * una anotación privada de otro profesional.
     *
     * La regla de privacidad tiene precedencia absoluta sobre cualquier
     * rol o jerarquía (docs/modulo-usuarios-permisos.md § 4.7).
     */
    #[Test]
    public function administrador_no_puede_leer_anotacion_privada_de_otro(): void
    {
        $autor         = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $administrador = $this->crearProfesionalEnUo('adm_sistema', $this->uo);
        $apunte        = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $administrador->can('view', $apunte),
            'Ni el adm_sistema puede leer una anotación privada de otro profesional.'
        );
    }

    /**
     * Un supervisor no puede leer una anotación privada de otro profesional,
     * aunque tenga acceso de lectura a toda la información de su ámbito.
     */
    #[Test]
    public function supervisor_no_puede_leer_anotacion_privada_de_otro(): void
    {
        $autor      = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $supervisor = $this->crearProfesionalEnUo('supervision', $this->uo);
        $apunte     = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $supervisor->can('view', $apunte),
            'El supervisor no puede leer anotaciones privadas de otros profesionales.'
        );
    }

    /**
     * El propio autor sí puede leer su propia anotación privada.
     */
    #[Test]
    public function autor_puede_leer_su_propia_anotacion_privada(): void
    {
        $autor  = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $apunte = $this->crearApuntePrivado($autor);

        $this->assertTrue(
            $autor->can('view', $apunte),
            'El autor debe poder leer su propia anotación privada.'
        );
    }

    /**
     * Nadie puede eliminar una anotación privada ajena, sin importar el rol.
     */
    #[Test]
    public function nadie_puede_eliminar_anotacion_privada_ajena(): void
    {
        $autor         = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $administrador = $this->crearProfesionalEnUo('adm_sistema', $this->uo);
        $apunte        = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $administrador->can('delete', $apunte),
            'Nadie puede eliminar una anotación privada que no sea suya.'
        );
    }

    /**
     * Un apunte NO privado sí puede ser leído por otro profesional
     * con el permiso apunte.leer_ajeno.
     */
    #[Test]
    public function apunte_no_privado_puede_ser_leido_por_otro_profesional(): void
    {
        $historia = \App\Models\HistoriaSocial::create([
            'ciudadano_id'           => 1,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        $autor  = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $colega = $this->crearProfesionalEnUo('intervencion', $this->uo);

        $apunte = Apunte::create([
            'historia_social_id' => $historia->id,
            'profesional_id'     => $autor->id,
            'tipo'               => 'anotacion',
            'privada'            => false,
        ]);

        $this->assertTrue(
            $colega->can('view', $apunte),
            'Un profesional con apunte.leer_ajeno puede leer apuntes no privados de colegas.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crea un usuario con el rol dado y lo adscribe a la UO indicada.
     *
     * @param string $nombreRol
     * @param UnidadOrganizativa $uo
     * @return User
     */
    private function crearProfesionalEnUo(string $nombreRol, UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create();
        $rol     = Role::findByName($nombreRol, 'web');

        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id'             => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo'           => 'interno',
            'fecha_inicio'           => Carbon::today(),
            'fecha_fin'              => null,
        ]);

        return $usuario;
    }

    /**
     * Crea un apunte privado cuyo autor es el usuario indicado.
     *
     * @param User $autor
     * @return Apunte
     */
    private function crearApuntePrivado(User $autor): Apunte
    {
        $historia = \App\Models\HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido'    => false,
            'estado'                 => 'abierta',
        ]);

        return Apunte::create([
            'historia_social_id' => $historia->id,
            'profesional_id'     => $autor->id,
            'tipo'               => 'anotacion',
            'privada'            => true,
        ]);
    }
}
