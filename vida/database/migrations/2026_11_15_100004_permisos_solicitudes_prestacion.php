<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de las solicitudes de prestación (fase 3 bis, paso 4; §5 del diseño) en una BD ya
 * sembrada. PermisosSeeder y RolesSeeder hacen lo mismo en una BD nueva.
 */
return new class extends Migration
{
    /** Permisos por rol. */
    private const ROLES = [
        'intervencion' => ['solicitud.crear', 'solicitud.ver', 'prestacion_persona.gestionar'],
        'tramitacion' => ['solicitud.tramitar', 'solicitud.registrar_manual', 'prestacion_persona.gestionar'],
        'supervision' => ['solicitud.crear', 'solicitud.ver', 'solicitud.tramitar', 'solicitud.registrar_manual', 'prestacion_persona.gestionar'],
    ];

    /**
     * Crea los permisos y los asigna a los roles que existan.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::table('roles')->doesntExist()) {
            return; // BD sin sembrar (tests): lo hacen los seeders
        }

        foreach (array_unique(array_merge(...array_values(self::ROLES))) as $nombre) {
            DB::table('permissions')->insertOrIgnore(['name' => $nombre, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (self::ROLES as $rol => $permisos) {
            $rolId = DB::table('roles')->where('name', $rol)->value('id');
            if ($rolId === null) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('name', $permisos)->pluck('id') as $permisoId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Quita los permisos.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_unique(array_merge(...array_values(self::ROLES))))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
