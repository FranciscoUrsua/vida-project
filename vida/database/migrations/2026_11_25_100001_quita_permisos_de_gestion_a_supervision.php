<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quita a `supervision` `centro.gestionar` y `configuracion.acceder`.
 *
 * `RolesSeeder` nunca se los ha dado, pero estaban en la BD compartida (añadidos a
 * mano, sin rastro en la auditoría). Con `centro.gestionar` cualquier dirección de
 * centro administraba todos los centros y tipos de centro en Filament
 * (`CentroPolicy::administra()`, `TipoCentroPolicy::administra()`), que es
 * capacidad de `adm_sistema` (docs/modulo-usuarios-permisos.md §2). La dirección
 * sigue editando su propio centro por `Centro::esDirigidoPor()`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = ['centro.gestionar', 'configuracion.acceder'];

    /**
     * Retira los permisos al rol.
     *
     * @return void
     */
    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'supervision')->value('id');
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
        $rolId = DB::table('roles')->where('name', 'supervision')->value('id');
        if ($rolId === null) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::PERMISOS)->pluck('id') as $permisoId) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rolId, 'permission_id' => $permisoId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
