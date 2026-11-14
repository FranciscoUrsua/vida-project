<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol `supervision` deja de leer planes (fase 3, decisión del desarrollador del 14 nov 2026).
 *
 * `supervision` por sí solo no lee ni edita planes de intervención: la dirección de un
 * centro o un responsable de UO superior que los necesite tiene además el rol
 * `intervencion`, que aporta `plan.leer`, `plan.crear` y `plan.editar`. RolesSeeder ya no
 * asigna el permiso; esta migración lo revoca donde estaba asignado.
 */
return new class extends Migration
{
    /**
     * Revoca `plan.leer` del rol `supervision`.
     *
     * @return void
     */
    public function up(): void
    {
        $rol = DB::table('roles')->where('name', 'supervision')->value('id');
        $permiso = DB::table('permissions')->where('name', 'plan.leer')->value('id');

        if ($rol !== null && $permiso !== null) {
            DB::table('role_has_permissions')->where('role_id', $rol)->where('permission_id', $permiso)->delete();
        }

        // Caché de permisos de spatie: se invalida para que el cambio surta efecto.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Devuelve `plan.leer` al rol `supervision`.
     *
     * @return void
     */
    public function down(): void
    {
        $rol = DB::table('roles')->where('name', 'supervision')->value('id');
        $permiso = DB::table('permissions')->where('name', 'plan.leer')->value('id');

        if ($rol !== null && $permiso !== null) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $rol, 'permission_id' => $permiso]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
