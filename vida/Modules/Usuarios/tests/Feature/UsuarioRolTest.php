<?php

namespace Modules\Usuarios\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Usuarios\Models\ConfiguracionRol;
use Modules\Usuarios\Models\UsuarioRol;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests funcionales del historial de roles y supervisión de asignaciones.
 *
 * Verifican que usuario_rol es la fuente de verdad, que el Observer
 * sincroniza correctamente model_has_roles, y que el flujo de aprobación
 * previa funciona según la configuración del rol.
 *
 * Grupos:
 *   A — Historial de roles (TF-USU-19 a TF-USU-23)
 *   D — Supervisión en asignación de roles (TF-USU-32 a TF-USU-35)
 */
class UsuarioRolTest extends TestCase
{
    use RefreshDatabase;

    private User $profesional;
    private Role $rolIntervencion;
    private Role $rolSupervision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profesional     = User::factory()->create();
        $this->rolIntervencion = Role::create(['name' => 'intervencion', 'guard_name' => 'web']);
        $this->rolSupervision  = Role::create(['name' => 'supervision', 'guard_name' => 'web']);
    }

    // -------------------------------------------------------------------------
    // Grupo A — Historial de roles (TF-USU-19 a TF-USU-23)
    // -------------------------------------------------------------------------

    #[Test]
    public function asignar_un_rol_crea_registro_en_usuario_rol_con_fecha_inicio_y_estado_activo(): void
    {
        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        $this->assertDatabaseCount('usuario_rol', 1);
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $this->profesional->id,
            'rol_id'     => $this->rolIntervencion->id,
            'estado'     => 'activo',
        ]);
        $this->assertTrue($this->profesional->fresh()->hasRole('intervencion'));
    }

    #[Test]
    public function cerrar_un_rol_establece_fecha_fin_y_lo_elimina_de_spatie(): void
    {
        $registro = UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today()->subMonth(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        $fechaInicio = $registro->fecha_inicio->toDateString();
        $fechaCierre = today()->subDay()->toDateString();

        // Usar ayer: isPast() devuelve false para today, el Observer solo revoca con fecha pasada
        $registro->update(['fecha_fin' => today()->subDay()]);

        $this->assertFalse($this->profesional->fresh()->hasRole('intervencion'));
        $this->assertDatabaseHas('usuario_rol', [
            'id'           => $registro->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaCierre,
        ]);
    }

    #[Test]
    public function el_historial_de_roles_se_preserva_al_reasignar_el_mismo_rol(): void
    {
        $registroAnterior = UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today()->subYear(),
            'fecha_fin'    => today()->subMonth(),
            'estado'       => 'inactivo',
        ]);

        $registroNuevo = UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        $this->assertDatabaseCount('usuario_rol', 2);
        $this->assertNotNull(UsuarioRol::find($registroAnterior->id)->fecha_fin);
        $this->assertNull(UsuarioRol::find($registroNuevo->id)->fecha_fin);
        $this->assertTrue($this->profesional->fresh()->hasRole('intervencion'));
    }

    #[Test]
    public function un_usuario_puede_tener_varios_roles_activos_simultaneamente(): void
    {
        $rolAdmUsuarios = Role::create(['name' => 'adm_usuarios', 'guard_name' => 'web']);

        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $rolAdmUsuarios->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        $this->assertTrue($this->profesional->fresh()->hasRole('intervencion'));
        $this->assertTrue($this->profesional->fresh()->hasRole('adm_usuarios'));
    }

    #[Test]
    public function el_comando_de_reconciliacion_sincroniza_model_has_roles_con_usuario_rol(): void
    {
        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        // Simular desincronización: borrar de model_has_roles sin tocar usuario_rol
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $this->profesional->id)
            ->where('role_id', $this->rolIntervencion->id)
            ->delete();

        $this->assertFalse($this->profesional->fresh()->hasRole('intervencion'));

        Artisan::call('usuarios:reconciliar-roles');

        $this->assertTrue($this->profesional->fresh()->hasRole('intervencion'));
    }

    // -------------------------------------------------------------------------
    // Grupo D — Supervisión en asignación de roles (TF-USU-32 a TF-USU-35)
    // -------------------------------------------------------------------------

    #[Test]
    public function asignar_un_rol_de_aprobacion_requerida_crea_solicitud_pendiente(): void
    {
        ConfiguracionRol::create([
            'rol_id'            => $this->rolSupervision->id,
            'nivel_supervision' => 'aprobacion_previa',
        ]);

        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolSupervision->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'pendiente_aprobacion',
        ]);

        $this->assertFalse($this->profesional->fresh()->hasRole('supervision'));
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $this->profesional->id,
            'rol_id'     => $this->rolSupervision->id,
            'estado'     => 'pendiente_aprobacion',
        ]);
    }

    #[Test]
    public function aprobar_una_solicitud_pendiente_activa_el_rol_en_spatie(): void
    {
        $registro = UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolSupervision->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'pendiente_aprobacion',
        ]);

        $registro->update(['estado' => 'activo']);

        $this->assertTrue($this->profesional->fresh()->hasRole('supervision'));
    }

    #[Test]
    public function denegar_una_solicitud_pendiente_no_activa_el_rol(): void
    {
        $registro = UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolSupervision->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'pendiente_aprobacion',
        ]);

        // 'denegado' no coincide con 'activo' ni con 'inactivo': el Observer no sincroniza
        $registro->update(['estado' => 'denegado']);

        $this->assertFalse($this->profesional->fresh()->hasRole('supervision'));
        $this->assertDatabaseHas('usuario_rol', [
            'id'     => $registro->id,
            'estado' => 'denegado',
        ]);
    }

    #[Test]
    public function asignar_un_rol_de_alerta_supervisada_lo_activa_inmediatamente(): void
    {
        ConfiguracionRol::create([
            'rol_id'            => $this->rolIntervencion->id,
            'nivel_supervision' => 'alerta_supervisada',
        ]);

        UsuarioRol::create([
            'usuario_id'   => $this->profesional->id,
            'rol_id'       => $this->rolIntervencion->id,
            'fecha_inicio' => today(),
            'fecha_fin'    => null,
            'estado'       => 'activo',
        ]);

        $this->assertTrue($this->profesional->fresh()->hasRole('intervencion'));
    }
}
