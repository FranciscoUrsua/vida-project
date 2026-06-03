<?php

namespace Tests\Feature;

use App\Models\AccesoProtegido;
use App\Models\Apunte;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use App\Models\UsuarioUo;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Intervencion\Services\HistoriaSocialService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de autorización en profundidad para datos sensibles de ciudadanos.
 *
 * Verifica la estrategia de seguridad en tres capas:
 *   1. GlobalScope AmbitoUoScope — filtrado automático por UO
 *   2. Policies — autorización granular por permiso y ámbito
 *   3. Servicios de dominio — punto central de escritura con autorización
 *
 * Base de datos: PostgreSQL (vida_testing), sin SQLite.
 * Roles definidos en RolesSeeder; permisos en PermisosSeeder.
 */
class AutorizacionDatosTest extends TestCase
{
    use RefreshDatabase;

    /** @var UnidadOrganizativa UO A — ámbito propio del usuario de intervención */
    private UnidadOrganizativa $uoA;

    /** @var UnidadOrganizativa UO B — UO externa para pruebas de Nivel 2/3 */
    private UnidadOrganizativa $uoB;

    /**
     * Prepara las UOs y siembra permisos y roles antes de cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->uoA = UnidadOrganizativa::create([
            'nombre' => 'CSS UO-A (test)',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->uoB = UnidadOrganizativa::create([
            'nombre' => 'CSS UO-B (test)',
            'tipo' => 'centro',
            'parent_id' => null,
            'activa' => true,
        ]);
    }

    // =========================================================================
    // SECCIÓN 1: Global Scopes
    // =========================================================================

    /**
     * Usuario intervencion en UO-A solo ve HistoriaSociales de UO-A (no de UO-B).
     */
    #[Test]
    public function global_scope_filtra_historias_de_uo_propia(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);

        // Crear historias en ambas UOs — sin GlobalScope
        $historiaUoA = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 1,
            'unidad_organizativa_id' => $this->uoA->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $historiaUoB = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 2,
            'unidad_organizativa_id' => $this->uoB->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // Actuar como el profesional de UO-A
        $this->actingAs($profesional);

        $historias = HistoriaSocial::all();

        $this->assertTrue(
            $historias->contains('id', $historiaUoA->id),
            'El profesional de UO-A debe ver la Historia de UO-A.'
        );

        $this->assertFalse(
            $historias->contains('id', $historiaUoB->id),
            'El profesional de UO-A no debe ver la Historia de UO-B (Nivel 2 es consulta, no browse automático).'
        );
    }

    /**
     * withoutGlobalScope(AmbitoUoScope::class) devuelve todos los registros.
     */
    #[Test]
    public function sin_global_scope_se_devuelven_todos_los_registros(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);

        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 100,
            'unidad_organizativa_id' => $this->uoA->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 200,
            'unidad_organizativa_id' => $this->uoB->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $this->actingAs($profesional);

        $todas = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->get();

        $this->assertGreaterThanOrEqual(
            2,
            $todas->count(),
            'Sin GlobalScope se deben devolver todos los registros, incluyendo los de otras UOs.'
        );
    }

    /**
     * Usuario sin UO activa no ve ningún registro con GlobalScope activo.
     */
    #[Test]
    public function usuario_sin_uo_activa_no_ve_registros(): void
    {
        $usuarioSinUo = User::factory()->create();
        $usuarioSinUo->assignRole(Role::findByName('intervencion', 'web'));
        // No se le asigna ninguna UO

        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 999,
            'unidad_organizativa_id' => $this->uoA->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        $this->actingAs($usuarioSinUo);

        $this->assertCount(
            0,
            HistoriaSocial::all(),
            'Un usuario sin UO activa no debe ver ninguna Historia Social.'
        );
    }

    /**
     * Sin usuario autenticado (consola), el GlobalScope no filtra nada.
     */
    #[Test]
    public function sin_usuario_autenticado_global_scope_no_filtra(): void
    {
        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 300,
            'unidad_organizativa_id' => $this->uoA->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => 400,
            'unidad_organizativa_id' => $this->uoB->id,
            'ciudadano_protegido' => false,
            'estado' => 'abierta',
        ]);

        // Sin actingAs → sin usuario autenticado
        $todas = HistoriaSocial::all();

        $this->assertGreaterThanOrEqual(
            2,
            $todas->count(),
            'Sin usuario autenticado (contexto de consola), el GlobalScope no debe filtrar.'
        );
    }

    // =========================================================================
    // SECCIÓN 2: Policies — casos positivos
    // =========================================================================

    /**
     * Profesional con historia.leer en su UO puede viewAny y view HistoriaSocial.
     */
    #[Test]
    public function profesional_intervencion_puede_ver_historia_de_su_uo(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->assertTrue(
            $profesional->can('viewAny', HistoriaSocial::class),
            'Profesional con historia.leer puede listar Historias Sociales.'
        );

        $this->assertTrue(
            $profesional->can('view', $historia),
            'Profesional con historia.leer puede ver Historia de su UO (Nivel 1).'
        );
    }

    /**
     * Profesional con historia.leer puede view HistoriaSocial de otra UO (Nivel 2).
     */
    #[Test]
    public function profesional_intervencion_puede_leer_historia_de_otra_uo_nivel_2(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historiaUoB = $this->crearHistoriaSinScope($this->uoB, ciudadanoProtegido: false);

        $this->assertTrue(
            $profesional->can('view', $historiaUoB),
            'Profesional con historia.leer puede consultar Historia de otra UO (Nivel 2 libre).'
        );
    }

    /**
     * Supervisor puede view HistoriaSocial de su ámbito de UO.
     */
    #[Test]
    public function supervisor_puede_ver_historia_de_su_ambito(): void
    {
        $supervisor = $this->crearUsuarioEnUo('supervision', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->assertTrue(
            $supervisor->can('view', $historia),
            'Supervisor puede ver Historia Social de su ámbito de UO.'
        );
    }

    /**
     * Profesional puede view su propio Apunte privado.
     */
    #[Test]
    public function profesional_puede_ver_su_propio_apunte_privado(): void
    {
        $autor = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);
        $apunte = $this->crearApunteSinScope($historia, $autor, privada: true);

        $this->assertTrue(
            $autor->can('view', $apunte),
            'El autor puede ver su propio apunte privado.'
        );
    }

    // =========================================================================
    // SECCIÓN 3: Policies — casos negativos (críticos)
    // =========================================================================

    /**
     * Profesional sin historia.editar no puede update aunque la Historia sea de su UO.
     */
    #[Test]
    public function profesional_sin_permiso_editar_no_puede_actualizar_historia(): void
    {
        // supervision tiene historia.leer pero NO historia.editar
        $supervisor = $this->crearUsuarioEnUo('supervision', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->assertFalse(
            $supervisor->can('update', $historia),
            'Usuario sin historia.editar no puede actualizar Historia Social (aunque sea de su UO).'
        );
    }

    /**
     * Verificación negativa: si se elimina la restricción de permiso, el test debe fallar.
     */
    #[Test]
    public function verificacion_negativa_restriccion_permiso_es_obligatoria(): void
    {
        // tramitacion tiene historia.abrir/cerrar pero NO historia.editar
        $tramitacion = $this->crearUsuarioEnUo('tramitacion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->assertFalse(
            $tramitacion->can('update', $historia),
            'El rol tramitacion no debe poder editar Historias Sociales.'
        );
    }

    /**
     * Profesional con historia.leer no puede update Historia de otra UO.
     */
    #[Test]
    public function profesional_no_puede_editar_historia_de_otra_uo(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historiaUoB = $this->crearHistoriaSinScope($this->uoB);

        $this->assertFalse(
            $profesional->can('update', $historiaUoB),
            'Profesional no puede editar Historia de una UO externa (solo lectura en Nivel 2).'
        );
    }

    /**
     * Profesional NO puede view Apunte privado de otro profesional.
     */
    #[Test]
    public function profesional_no_puede_ver_apunte_privado_de_otro(): void
    {
        $autor = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $colega = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);
        $apuntePrivado = $this->crearApunteSinScope($historia, $autor, privada: true);

        $this->assertFalse(
            $colega->can('view', $apuntePrivado),
            'Un profesional no puede ver el apunte privado de otro, sin importar su rol.'
        );
    }

    /**
     * Ni adm_sistema puede ver Apunte privado de otro profesional.
     */
    #[Test]
    public function adm_sistema_no_puede_ver_apunte_privado_de_otro(): void
    {
        $autor = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $admSist = $this->crearUsuarioEnUo('adm_sistema', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);
        $apuntePrivado = $this->crearApunteSinScope($historia, $autor, privada: true);

        $this->assertFalse(
            $admSist->can('view', $apuntePrivado),
            'Ni adm_sistema puede ver anotaciones privadas de otro profesional.'
        );
    }

    /**
     * Profesional no puede view Ciudadano de colectivo protegido de otra UO sin AccesoProtegido.
     */
    #[Test]
    public function profesional_no_puede_ver_ciudadano_protegido_sin_acceso_aprobado(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);

        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->create([
            'nombre' => 'Ana',
            'apellido1' => 'García',
            'fecha_nacimiento' => '1980-01-01',
            'sexo' => 'mujer',
            'activo' => true,
            'es_vvg' => false,
            'es_psh' => false,
            'colectivo_extra_protegido' => true, // Marcado como especialmente protegido
            'nivel_identificacion' => 'basico',
        ]);

        // La Historia del ciudadano está en UO-B (fuera del ámbito del profesional)
        HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => $ciudadano->id,
            'unidad_organizativa_id' => $this->uoB->id,
            'ciudadano_protegido' => true,
            'estado' => 'abierta',
        ]);

        $this->assertFalse(
            $profesional->can('view', $ciudadano),
            'Sin AccesoProtegido aprobado, no se puede ver ciudadano protegido de otra UO.'
        );
    }

    /**
     * Usuario con rol tramitacion no puede view Apunte (no tiene apunte.leer).
     */
    #[Test]
    public function tramitacion_no_puede_ver_apuntes(): void
    {
        $tramitacion = $this->crearUsuarioEnUo('tramitacion', $this->uoA);
        $autor = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);
        $apunte = $this->crearApunteSinScope($historia, $autor, privada: false);

        $this->assertFalse(
            $tramitacion->can('view', $apunte),
            'El rol tramitacion no tiene apunte.leer y no puede ver ningún apunte.'
        );
    }

    /**
     * Supervisor no puede crear ni editar Historias Sociales.
     */
    #[Test]
    public function supervisor_no_puede_escribir_datos_de_ciudadanos(): void
    {
        $supervisor = $this->crearUsuarioEnUo('supervision', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->assertFalse(
            $supervisor->can('create', HistoriaSocial::class),
            'El rol supervision no puede crear Historias Sociales.'
        );

        $this->assertFalse(
            $supervisor->can('update', $historia),
            'El rol supervision no puede editar Historias Sociales.'
        );
    }

    // =========================================================================
    // SECCIÓN 4: Servicios de dominio
    // =========================================================================

    /**
     * actualizar() sin permiso lanza AuthorizationException.
     */
    #[Test]
    public function servicio_historia_social_lanza_excepcion_sin_permiso(): void
    {
        // tramitacion no tiene historia.editar
        $tramitacion = $this->crearUsuarioEnUo('tramitacion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->actingAs($tramitacion);

        $this->expectException(AuthorizationException::class);

        $servicio = new HistoriaSocialService;
        $servicio->actualizar($historia->id, ['estado' => 'cerrada']);
    }

    /**
     * actualizar() con permiso correcto actualiza el registro.
     */
    #[Test]
    public function servicio_historia_social_permite_actualizacion_con_permiso(): void
    {
        $profesional = $this->crearUsuarioEnUo('intervencion', $this->uoA);
        $historia = $this->crearHistoriaSinScope($this->uoA);

        $this->actingAs($profesional);

        $servicio = new HistoriaSocialService;
        $historiaActualizada = $servicio->actualizar($historia->id, ['estado' => 'en_seguimiento']);

        $this->assertEquals('en_seguimiento', $historiaActualizada->estado);
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Crea un usuario con el rol dado y lo adscribe a la UO indicada.
     *
     * @param string $nombreRol Nombre del rol (ej: 'intervencion')
     * @param UnidadOrganizativa $uo UO de adscripción
     */
    private function crearUsuarioEnUo(string $nombreRol, UnidadOrganizativa $uo): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(Role::findByName($nombreRol, 'web'));

        UsuarioUo::create([
            'usuario_id' => $usuario->id,
            'unidad_organizativa_id' => $uo->id,
            'tipo_vinculo' => 'interno',
            'fecha_inicio' => Carbon::today(),
            'fecha_fin' => null,
        ]);

        return $usuario;
    }

    /**
     * Crea una Historia Social sin GlobalScope (para setup de fixtures).
     *
     * @param UnidadOrganizativa $uo UO responsable
     * @param bool $ciudadanoProtegido Si el ciudadano es especialmente protegido
     */
    private function crearHistoriaSinScope(
        UnidadOrganizativa $uo,
        bool $ciudadanoProtegido = false
    ): HistoriaSocial {
        return HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)->create([
            'ciudadano_id' => fake()->unique()->numberBetween(1, 99999),
            'unidad_organizativa_id' => $uo->id,
            'ciudadano_protegido' => $ciudadanoProtegido,
            'estado' => 'abierta',
        ]);
    }

    /**
     * Crea un Apunte sin GlobalScope (para setup de fixtures).
     *
     * @param HistoriaSocial $historia Historia Social asociada
     * @param User $autor Autor del apunte
     * @param bool $privada Si el apunte es privado
     */
    private function crearApunteSinScope(
        HistoriaSocial $historia,
        User $autor,
        bool $privada
    ): Apunte {
        return Apunte::withoutGlobalScope(AmbitoUoScope::class)->create([
            'historia_social_id' => $historia->id,
            'profesional_id' => $autor->id,
            'tipo' => 'anotacion',
            'privada' => $privada,
        ]);
    }
}
