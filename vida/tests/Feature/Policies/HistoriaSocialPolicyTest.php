<?php

namespace Tests\Feature\Policies;

use App\Models\AccesoProtegido;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Models\UsuarioUo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de la HistoriaSocialPolicy.
 *
 * Verifica el modelo de acceso de tres niveles definido en
 * docs/modulo-usuarios-permisos.md secciones 1.4 y 4.4:
 *
 *   Nivel 1 — Gestión completa: misma UO → puede editar y leer.
 *   Nivel 2 — Consulta libre: UO distinta, ciudadano no protegido → puede leer.
 *   Nivel 3 — Acceso con aprobación: UO distinta, ciudadano protegido → requiere aprobación.
 */
class HistoriaSocialPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizativa $uoA;
    private UnidadOrganizativa $uoB;

    /**
     * Prepara la estructura de UO y roles necesaria para los tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermisosSeeder::class);
        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create([
            'nombre'    => 'CSS Arganzuela',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);

        $this->uoB = UnidadOrganizativa::create([
            'nombre'    => 'CSS Retiro',
            'tipo'      => 'centro',
            'parent_id' => null,
            'activa'    => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Caso 1: intervencion en UO A puede editar Historia de UO A (Nivel 1)
    // -------------------------------------------------------------------------

    /**
     * Un profesional con rol intervencion adscrito a UO A puede editar
     * una Historia Social que pertenece a UO A (Nivel 1: gestión completa).
     */
    #[Test]
    public function profesional_intervencion_puede_editar_historia_de_su_uo(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia    = $this->crearHistoria($this->uoA, protegido: false);

        $this->assertTrue(
            $profesional->can('update', $historia),
            'Un profesional con rol intervencion debe poder editar una Historia de su propia UO.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 2: intervencion en UO A puede leer Historia de UO B (Nivel 2)
    // -------------------------------------------------------------------------

    /**
     * Un profesional con rol intervencion adscrito a UO A puede consultar
     * una Historia Social de UO B si el ciudadano no está protegido (Nivel 2).
     */
    #[Test]
    public function profesional_intervencion_puede_leer_historia_de_otra_uo(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia    = $this->crearHistoria($this->uoB, protegido: false);

        $this->assertTrue(
            $profesional->can('view', $historia),
            'Un profesional con rol intervencion debe poder consultar Historias de otras UO (Nivel 2).'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 5: Acceso a ciudadano protegido sin aprobación → denegado (Nivel 3)
    // -------------------------------------------------------------------------

    /**
     * Un profesional NO puede consultar la Historia de un ciudadano protegido
     * en otra UO si no tiene aprobación de acceso vigente.
     */
    #[Test]
    public function acceso_a_ciudadano_protegido_sin_aprobacion_es_denegado(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $historia    = $this->crearHistoria($this->uoB, protegido: true);

        $this->assertFalse(
            $profesional->can('view', $historia),
            'Sin aprobación vigente, no se debe permitir el acceso a ciudadano protegido.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 6: Acceso a ciudadano protegido con aprobación vigente → permitido
    // -------------------------------------------------------------------------

    /**
     * Un profesional SÍ puede consultar la Historia de un ciudadano protegido
     * en otra UO si existe una aprobación de acceso vigente.
     */
    #[Test]
    public function acceso_a_ciudadano_protegido_con_aprobacion_vigente_es_permitido(): void
    {
        $profesional = $this->crearProfesionalEnUo('intervencion', $this->uoA);
        $supervisor  = $this->crearProfesionalEnUo('supervision', $this->uoB);
        $historia    = $this->crearHistoria($this->uoB, protegido: true);

        AccesoProtegido::create([
            'usuario_id'          => $profesional->id,
            'ciudadano_id'        => $historia->ciudadano_id,
            'solicitante_id'      => $profesional->id,
            'justificacion'       => 'Coordinación urgente por caso de derivación',
            'estado'              => 'aprobado',
            'aprobado_por'        => $supervisor->id,
            'fecha_resolucion'    => now(),
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
        $supervisor  = $this->crearProfesionalEnUo('supervision', $this->uoB);
        $historia    = $this->crearHistoria($this->uoB, protegido: true);

        AccesoProtegido::create([
            'usuario_id'          => $profesional->id,
            'ciudadano_id'        => $historia->ciudadano_id,
            'solicitante_id'      => $profesional->id,
            'justificacion'       => 'Acceso puntual ya finalizado',
            'estado'              => 'aprobado',
            'aprobado_por'        => $supervisor->id,
            'fecha_resolucion'    => Carbon::yesterday()->subDay(),
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
     * Crea una Historia Social en la UO indicada.
     *
     * @param UnidadOrganizativa $uo
     * @param bool $protegido
     * @return HistoriaSocial
     */
    private function crearHistoria(UnidadOrganizativa $uo, bool $protegido): HistoriaSocial
    {
        return HistoriaSocial::create([
            'ciudadano_id'           => fake()->numberBetween(1, 9999),
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido'    => $protegido,
            'estado'                 => 'abierta',
        ]);
    }
}
