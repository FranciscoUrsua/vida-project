<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quita a `tramitacion` `historia.cerrar`.
 *
 * Cerrar o reactivar una Historia Social es, como abrirla, un acto profesional del
 * trabajador social: solo lo hace un profesional con rol `intervencion` (decisión del
 * 25 nov 2026, que completa la de la migración `2026_11_25_100005`). Tramitación
 * conserva `historia.ver_estado`: consulta si la persona tiene historia y en qué estado.
 */
return new class extends Migration
{
    /** Permiso que se retira. */
    private const PERMISO = 'historia.cerrar';

    /**
     * Retira el permiso al rol.
     *
     * @return void
     */
    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'tramitacion')->value('id');
        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->value('id');
        if ($rolId === null || $permisoId === null) {
            return;
        }

        DB::table('role_has_permissions')->where('role_id', $rolId)->where('permission_id', $permisoId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Devuelve el permiso al rol.
     *
     * @return void
     */
    public function down(): void
    {
        $rolId = DB::table('roles')->where('name', 'tramitacion')->value('id');
        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->value('id');
        if ($rolId === null || $permisoId === null) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
