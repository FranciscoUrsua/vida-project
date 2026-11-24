<?php

namespace Modules\Ciudadania\Tests\Feature\ConsultaBasica;

use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Permisos de la fase 4 (paso 1): seeder aditivo y migración para la BD ya sembrada.
 *
 * TF-CBA-29 y 29 bis.
 *
 * @see docs/instrucciones-cli/2026-11-fase4-consulta-basica.md
 */
class PermisosConsultaBasicaTest extends TestCase
{
    use RefreshDatabase;

    /** Permisos del paso 1 por rol. */
    private const ESPERADOS = [
        'consulta_basica' => ['ciudadano.crear', 'ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial', 'documento.subir', 'documento.gestionar_propios'],
        'tramitacion' => ['ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial', 'citas.solicitar', 'citas.gestionar', 'documento.subir', 'documento.gestionar_propios'],
        'intervencion' => ['historia.ver_estado', 'cita.ver_historial', 'ciudadano.editar_relaciones', 'documento.ver_carpeta', 'documento.subir'],
        // Sin `documento.ver_carpeta` desde R2 (24 nov 2026): la supervisión sola no ve documentos.
        'supervision' => ['historia.ver_estado', 'cita.ver_historial'],
    ];

    /**
     * Permisos de un rol.
     *
     * @param string $rol
     *
     * @return list<string>
     */
    private function permisosDe(string $rol): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Role::findByName($rol)->permissions()->pluck('name')->all();
    }

    /** TF-CBA-29 — Seeder de permisos: los del paso 1, sin duplicar ni quitar lo del backoffice. */
    #[Test]
    public function tf_cba_29_seeder_de_permisos(): void
    {
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        foreach (self::ESPERADOS as $rol => $permisos) {
            foreach ($permisos as $permiso) {
                $this->assertContains($permiso, $this->permisosDe($rol), "{$rol} debería tener {$permiso}");
            }
        }
        // Consulta básica y tramitación no ven la carpeta completa
        $this->assertNotContains('documento.ver_carpeta', $this->permisosDe('consulta_basica'));
        $this->assertNotContains('documento.ver_carpeta', $this->permisosDe('tramitacion'));
        // Ni la supervisión sola (R2)
        $this->assertNotContains('documento.ver_carpeta', $this->permisosDe('supervision'));

        // Configuración del backoffice: se quita uno y se añade otro al rol
        Role::findByName('consulta_basica')->givePermissionTo('trazabilidad.consultar');
        $total = DB::table('role_has_permissions')->count();
        $permisos = Permission::count();

        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->assertSame($permisos, Permission::count(), 'No duplica permisos');
        $this->assertSame($total, DB::table('role_has_permissions')->count(), 'No duplica asignaciones');
        $this->assertContains('trazabilidad.consultar', $this->permisosDe('consulta_basica'), 'No quita lo configurado en el backoffice');
    }

    /** TF-CBA-29 bis — La migración de la BD sembrada añade los permisos sin quitar nada. */
    #[Test]
    public function tf_cba_29_bis_la_migracion_anade_sin_quitar(): void
    {
        $this->seed(PermisosSeeder::class);
        $this->seed(RolesSeeder::class);

        // Simula una BD anterior a la fase: sin los permisos nuevos y con algo propio del backoffice
        Permission::whereIn('name', ['historia.ver_estado', 'cita.ver_historial', 'documento.ver_carpeta', 'documento.subir', 'documento.gestionar_propios', 'ciudadano.editar_relaciones'])->delete();
        Role::findByName('tramitacion')->revokePermissionTo(['citas.solicitar', 'citas.gestionar']);
        Role::findByName('consulta_basica')->givePermissionTo('trazabilidad.consultar');

        $migracion = require database_path('migrations/2026_11_15_130001_permisos_consulta_basica_fase4.php');
        $migracion->up();
        $migracion->up(); // idempotente

        foreach (self::ESPERADOS as $rol => $permisos) {
            foreach ($permisos as $permiso) {
                $this->assertContains($permiso, $this->permisosDe($rol), "{$rol} debería tener {$permiso}");
            }
        }
        $this->assertContains('trazabilidad.consultar', $this->permisosDe('consulta_basica'));
        $this->assertSame(1, Permission::where('name', 'documento.subir')->count());
    }
}
