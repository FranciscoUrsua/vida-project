<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de superficie (R4.4) y supervisión sola sin Historia Social ni documentos (R2),
 * en una BD ya sembrada. PermisosSeeder y RolesSeeder hacen lo mismo en una BD nueva.
 *
 * - Crea los permisos que deciden las superficies y su menú: `supervision.panel`,
 *   `agenda.ver_propia`, `tramitacion.bandeja` y `ciudadano.buscar`, y los da a los roles que
 *   hoy entran en esas pantallas por el middleware `role:`, para que nadie gane ni pierda acceso.
 * - Quita a `supervision` `historia.leer`, `apunte.leer`, `apunte.leer_ajeno` y
 *   `documento.ver_carpeta` (decisión R2 del 23 nov 2026, confirmada por el desarrollador el
 *   24 nov): la supervisión sola ve la ficha en lectura, sin Historia Social, apuntes ni
 *   documentos. Quien además tiene `intervencion` los conserva por ese rol.
 */
return new class extends Migration
{
    /** Permisos nuevos (su descripción está en PermisosSeeder). */
    private const NUEVOS = ['supervision.panel', 'agenda.ver_propia', 'tramitacion.bandeja', 'ciudadano.buscar'];

    /** Permisos nuevos por rol. */
    private const ROLES = [
        'supervision' => ['supervision.panel', 'ciudadano.buscar'],
        'intervencion' => ['agenda.ver_propia', 'ciudadano.buscar'],
        'tramitacion' => ['tramitacion.bandeja', 'ciudadano.buscar'],
        'consulta_basica' => ['ciudadano.buscar'],
    ];

    /** Permisos que pierde `supervision` (R2). */
    private const QUITAR_A_SUPERVISION = ['historia.leer', 'apunte.leer', 'apunte.leer_ajeno', 'documento.ver_carpeta'];

    /**
     * Crea y asigna los permisos de superficie y retira los de R2.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::table('roles')->doesntExist()) {
            return; // BD sin sembrar (tests): lo hacen los seeders
        }

        foreach (self::NUEVOS as $nombre) {
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

        $supervision = DB::table('roles')->where('name', 'supervision')->value('id');
        if ($supervision !== null) {
            DB::table('role_has_permissions')
                ->where('role_id', $supervision)
                ->whereIn('permission_id', DB::table('permissions')->whereIn('name', self::QUITAR_A_SUPERVISION)->pluck('id'))
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Quita los permisos nuevos y devuelve a `supervision` los de R2.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::NUEVOS)->delete();

        $rolId = DB::table('roles')->where('name', 'supervision')->value('id');
        if ($rolId !== null) {
            foreach (DB::table('permissions')->whereIn('name', self::QUITAR_A_SUPERVISION)->pluck('id') as $permisoId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
