<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra el rol 'supervisor' a 'supervision' para unificar el nombre canónico.
 *
 * El rol 'supervisor' fue creado accidentalmente por los mundos de demo,
 * que usaban ese nombre en lugar del canónico 'supervision' definido en RolesSeeder.
 * Todo el código de producción ya usa 'supervision'; esta migración alinea la BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Reasignar en model_has_roles las asignaciones del rol 'supervisor' al rol 'supervision'
        $rolSupervision = DB::table('roles')->where('name', 'supervision')->where('guard_name', 'web')->first();
        $rolSupervisor  = DB::table('roles')->where('name', 'supervisor')->where('guard_name', 'web')->first();

        if ($rolSupervisor && $rolSupervision) {
            // Mover asignaciones evitando duplicados
            DB::table('model_has_roles')
                ->where('role_id', $rolSupervisor->id)
                ->whereNotIn('model_id', function ($sub) use ($rolSupervision) {
                    $sub->select('model_id')
                        ->from('model_has_roles')
                        ->where('role_id', $rolSupervision->id);
                })
                ->update(['role_id' => $rolSupervision->id]);

            // Eliminar asignaciones sobrantes del rol antiguo
            DB::table('model_has_roles')->where('role_id', $rolSupervisor->id)->delete();

            // Eliminar permisos asociados al rol antiguo
            DB::table('role_has_permissions')->where('role_id', $rolSupervisor->id)->delete();

            // Eliminar el rol 'supervisor' ya vacío
            DB::table('roles')->where('id', $rolSupervisor->id)->delete();
        } elseif ($rolSupervisor && ! $rolSupervision) {
            // Si solo existe 'supervisor', renombrarlo directamente
            DB::table('roles')->where('id', $rolSupervisor->id)->update(['name' => 'supervision']);
        }

        // Limpiar la caché de permisos de Spatie
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se revierte: mantener 'supervision' como nombre canónico
    }
};
