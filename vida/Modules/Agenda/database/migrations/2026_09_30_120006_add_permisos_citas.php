<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos atómicos de citas (docs/modulo-citas.md §7) y su asignación a roles.
 *
 * El despliegue solo ejecuta migraciones: sin esta, los permisos no llegarían a
 * la BD compartida. Añade sin quitar nada (la configuración de roles se ajusta
 * a mano en staging). Los mismos valores están en PermisosSeeder y RolesSeeder.
 */
return new class extends Migration
{
    /** Permiso => descripción. */
    private const PERMISOS = [
        'citas.solicitar' => 'Crear solicitudes de cita',
        'citas.gestionar' => 'Dar, reprogramar y cancelar citas; bandeja de citación',
        'citas.atender' => 'Marcar incomparecencias, registrar acompañantes y pedir cambios en las citas propias',
        'citas.supervisar' => 'Reasignar por ausencia, cancelación retroactiva y correcciones de marcado',
    ];

    /** Rol => permisos. */
    private const ROLES = [
        'consulta_basica' => ['citas.solicitar', 'citas.gestionar'],
        'intervencion' => ['citas.solicitar', 'citas.atender'],
        'supervision' => ['citas.solicitar', 'citas.gestionar', 'citas.atender', 'citas.supervisar'],
    ];

    /**
     * Crea los permisos y los asigna.
     *
     * @return void
     */
    public function up(): void
    {
        $ahora = now();

        foreach (self::PERMISOS as $nombre => $descripcion) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $nombre, 'guard_name' => 'web'],
                ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }

        foreach (self::ROLES as $rol => $permisos) {
            $rolId = DB::table('roles')->where('name', $rol)->where('guard_name', 'web')->value('id');
            if ($rolId === null) {
                continue;
            }

            foreach ($permisos as $permiso) {
                $permisoId = DB::table('permissions')->where('name', $permiso)->where('guard_name', 'web')->value('id');
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $rolId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Elimina los permisos y sus asignaciones.
     *
     * @return void
     */
    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISOS))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
