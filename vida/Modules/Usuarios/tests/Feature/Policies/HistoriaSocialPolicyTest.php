<?php

namespace Modules\Usuarios\Tests\Feature\Policies;

use App\Models\AccesoProtegido;
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
 * Tests de la HistoriaSocialPolicy del módulo Usuarios.
 *
 * Verifica el modelo de acceso de tres niveles definido en
 * docs/modulo-usuarios-permisos.md secciones 1.4 y 4.4.
 */
class HistoriaSocialPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;

    private UnidadOrganizativa $uoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create([
            'nombre' => 'CSS Arganzuela',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->uoB = UnidadOrganizativa::create([
            'nombre' => 'CSS Retiro',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);
    }

    /**
     * Un profesional con rol intervencion adscrito a UO A puede editar
     * una Historia Social que pertenece a UO A (Nivel 1: gestión completa).
     */
    #[Test]
    public function profesional_intervencion_puede_editar_historia_de_su_uo(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoria($this->uoA, protegido: false);

        $this->assertTrue(
            $profesional->can('update', $historia),
            'Un profesional con rol intervencion debe poder editar una Historia de su propia UO.'
        );
    }

    /**
     * Un profesional con rol intervencion adscrito a UO A puede consultar
     * una Historia Social de UO B si el ciudadano no está protegido (Nivel 2).
     */
    #[Test]
    public function profesional_intervencion_puede_leer_historia_de_otra_uo(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoria($this->uoB, protegido: false);

        $this->assertTrue(
            $profesional->can('view', $historia),
            'Un profesional con rol intervencion debe poder consultar Historias de otras UO (Nivel 2).'
        );
    }

    /**
     * Un profesional con rol tramitacion no puede editar Historias Sociales.
     * El rol tramitacion no incluye el permiso historia.editar.
     */
    #[Test]
    public function tramitacion_no_puede_editar_historia(): void
    {
        $profesional = $this->crearProfesionalEnUo('tramitacion', $this->uoA);
        $historia = $this->crearHistoria($this->uoA, protegido: false);

        $this->assertFalse(
            $profesional->can('update', $historia),
            'El rol tramitacion no debe poder editar Historias Sociales.'
        );
    }

    /**
     * Un profesional NO puede consultar la Historia de un ciudadano protegido
     * en otra UO si no tiene aprobación de acceso vigente.
     */
    #[Test]
    public function acceso_a_ciudadano_protegido_sin_aprobacion_es_denegado(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoria($this->uoB, protegido: true);

        $this->assertFalse(
            $profesional->can('view', $historia),
            'Sin aprobación vigente, no se debe permitir el acceso a ciudadano protegido.'
        );
    }

    /**
     * Un profesional SÍ puede consultar la Historia de un ciudadano protegido
     * en otra UO si existe una aprobación de acceso vigente.
     */
    #[Test]
    public function acceso_a_ciudadano_protegido_con_aprobacion_vigente_es_permitido(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $supervisor = $this->crearProfesionalEnUo('supervision', $this->uoB);
        $historia = $this->crearHistoria($this->uoB, protegido: true);

        AccesoProtegido::create([
            'usuario_id' => $profesional->id,
            'ciudadano_id' => $historia->ciudadano_id,
            'solicitante_id' => $profesional->id,
            'justificacion' => 'Coordinación urgente por caso de derivación',
            'estado' => 'aprobado',
            'aprobado_por' => $supervisor->id,
            'fecha_resolucion' => now(),
            'acceso_valido_hasta' => Carbon::tomorrow(),
        ]);

        $this->assertTrue(
            $profesional->can('view', $historia),
            'Con aprobación vigente, debe permitirse el acceso a ciudadano protegido.'
        );
    }

    /**
     * Una aprobación expirada no habilita el acceso a un ciudadano protegido.
     */
    #[Test]
    public function aprobacion_expirada_no_habilita_acceso_a_ciudadano_protegido(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $supervisor = $this->crearProfesionalEnUo('supervision', $this->uoB);
        $historia = $this->crearHistoria($this->uoB, protegido: true);

        AccesoProtegido::create([
            'usuario_id' => $profesional->id,
            'ciudadano_id' => $historia->ciudadano_id,
            'solicitante_id' => $profesional->id,
            'justificacion' => 'Acceso puntual ya finalizado',
            'estado' => 'aprobado',
            'aprobado_por' => $supervisor->id,
            'fecha_resolucion' => Carbon::yesterday()->subDay(),
            'acceso_valido_hasta' => Carbon::yesterday(),
        ]);

        $this->assertFalse(
            $profesional->can('view', $historia),
            'Una aprobación expirada no debe habilitar el acceso.'
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

    private function crearHistoria(UnidadOrganizativa $uo, bool $protegido): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id' => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => $protegido,
            'estado' => 'abierta',
        ]);
    }
}
