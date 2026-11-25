<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quita a `tramitacion` `historia.abrir` e `historia.crear`.
 *
 * Abrir una Historia Social es un acto profesional del trabajador social: solo lo
 * hace un profesional con rol `intervencion`, que en los centros «quien abre» queda
 * como profesional de referencia. `RolesSeeder` daba esos permisos a `tramitacion`
 * por error y, desde la ficha de la persona, un tramitador abrió una historia y quedó
 * como referencia (detectado en staging el 25 nov 2026). La policy ya exige el rol
 * intervención; esto deja los permisos coherentes con ella.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = ['historia.abrir', 'historia.crear'];

    /**
     * Retira los permisos al rol.
     *
     * @return void
     */
    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'tramitacion')->value('id');
        if ($rolId === null) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('role_id', $rolId)
            ->whereIn('permission_id', DB::table('permissions')->whereIn('name', self::PERMISOS)->select('id'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Devuelve los permisos al rol.
     *
     * @return void
     */
    public function down(): void
    {
        $rolId = DB::table('roles')->where('name', 'tramitacion')->value('id');
        if ($rolId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::PERMISOS)->pluck('id') as $permisoId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
