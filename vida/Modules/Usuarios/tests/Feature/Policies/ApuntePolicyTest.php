<?php

namespace Modules\Usuarios\Tests\Feature\Policies;

use App\Models\Apunte;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de la ApuntePolicy del módulo Usuarios.
 *
 * Verifica la regla especial de anotaciones privadas
 * (docs/modulo-usuarios-permisos.md § 4.7).
 */
class ApuntePolicyTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uo = UnidadOrganizativa::create([
            'nombre' => 'CSS Pruebas',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);
    }

    /**
     * Un usuario con rol tramitacion no tiene el permiso apunte.crear.
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

    /**
     * El propio autor sí puede leer su propia anotación privada.
     */
    #[Test]
    public function autor_puede_leer_su_propia_anotacion_privada(): void
    {
        $autor = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $apunte = $this->crearApuntePrivado($autor);

        $this->assertTrue(
            $autor->can('view', $apunte),
            'El autor debe poder leer su propia anotación privada.'
        );
    }

    /**
     * Ningún otro usuario, incluido adm_sistema, puede leer anotación privada ajena.
     */
    #[Test]
    public function administrador_no_puede_leer_anotacion_privada_de_otro(): void
    {
        $autor = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $administrador = $this->crearProfesionalEnUo('adm_sistema', $this->uo);
        $apunte = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $administrador->can('view', $apunte),
            'Ni el adm_sistema puede leer una anotación privada de otro profesional.'
        );
    }

    /**
     * El supervisor no puede leer anotaciones privadas ajenas.
     */
    #[Test]
    public function supervisor_no_puede_leer_anotacion_privada_de_otro(): void
    {
        $autor = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $supervisor = $this->crearProfesionalEnUo('supervision', $this->uo);
        $apunte = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $supervisor->can('view', $apunte),
            'El supervisor no puede leer anotaciones privadas de otros profesionales.'
        );
    }

    /**
     * Nadie puede eliminar una anotación privada ajena.
     */
    #[Test]
    public function nadie_puede_eliminar_anotacion_privada_ajena(): void
    {
        $autor = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $administrador = $this->crearProfesionalEnUo('adm_sistema', $this->uo);
        $apunte = $this->crearApuntePrivado($autor);

        $this->assertFalse(
            $administrador->can('delete', $apunte),
            'Nadie puede eliminar una anotación privada que no sea suya.'
        );
    }

    /**
     * Un apunte NO privado puede ser leído por otro profesional con apunte.leer_ajeno.
     */
    #[Test]
    public function apunte_no_privado_puede_ser_leido_por_otro_profesional(): void
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id' => 1,
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $autor = $this->crearProfesionalEnUo('intervencion', $this->uo);
        $colega = $this->crearProfesionalEnUo('intervencion', $this->uo);

        $apunte = Apunte::create([
            'historia_social_id' => $historia->id,
            'profesional_id' => $autor->id,
            'tipo' => 'anotacion',
            'privada' => false,
        ]);

        $this->assertTrue(
            $colega->can('view', $apunte),
            'Un profesional con apunte.leer_ajeno puede leer apuntes no privados de colegas.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearProfesionalEnUo(string $nombreRol, UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create();
        $rol = Role::findByName($nombreRol, 'web');
        $usuario->assignRole($rol);

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => Carbon::today(),
            'fecha_fin' => null,
        ]);

        return $usuario;
    }

    private function crearApuntePrivado(User $autor): Apunte
    {
        $historia = HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $this->uo->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        return Apunte::create([
            'historia_social_id' => $historia->id,
            'profesional_id' => $autor->id,
            'tipo' => 'anotacion',
            'privada' => true,
        ]);
    }
}
