<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Agenda y citas del profesional de encargo (bloque E1 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md, EP-9): `consulta_profesional` recibe
 * `agenda.ver_propia`, `citas.solicitar` y `citas.atender`. Mismo resultado que `RolesSeeder`.
 */
return new class extends Migration
{
    /** @var list<string> Permisos que se añaden al rol. */
    private const PERMISOS = ['agenda.ver_propia', 'citas.solicitar', 'citas.atender'];

    /**
     * Añade los permisos al rol (sin tocar los que ya tiene).
     *
     * @return void
     */
    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'consulta_profesional')->value('id');
        if ($rolId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->pluck('id') as $permisoId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Retira los permisos del rol.
     *
     * @return void
     */
    public function down(): void
    {
        $rolId = DB::table('roles')->where('name', 'consulta_profesional')->value('id');
        if ($rolId === null) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->pluck('id');
        DB::table('role_has_permissions')->where('role_id', $rolId)->whereIn('permission_id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
