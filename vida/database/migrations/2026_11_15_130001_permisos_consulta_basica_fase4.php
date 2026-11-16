<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de la fase 4 (atención administrativa, `docs/instrucciones-cli/2026-11-fase4-consulta-basica.md`
 * paso 1) en una BD ya sembrada. PermisosSeeder y RolesSeeder hacen lo mismo en una BD nueva.
 *
 * Solo añade: no quita a ningún rol lo que tenga configurado en el backoffice.
 * `documento.ver_carpeta` se da a los roles que hoy ven la carpeta completa, para que
 * nadie la pierda; `consulta_basica` y `tramitacion` pasan a ver solo sus documentos del día.
 */
return new class extends Migration
{
    /** Permisos por rol. */
    private const ROLES = [
        'adm_sistema' => ['documento.ver_carpeta', 'documento.subir'],
        'supervision' => ['historia.ver_estado', 'cita.ver_historial', 'documento.ver_carpeta'],
        'intervencion' => ['ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial', 'documento.ver_carpeta', 'documento.subir'],
        'tramitacion' => [
            'ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial', 'citas.solicitar', 'citas.gestionar',
            'documento.subir', 'documento.gestionar_propios',
        ],
        'consulta_profesional' => ['documento.ver_carpeta'],
        'consulta_basica' => [
            'ciudadano.crear', 'ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial',
            'documento.subir', 'documento.gestionar_propios',
        ],
    ];

    /** Permisos que crea esta migración (los demás ya existían). */
    private const NUEVOS = [
        'ciudadano.editar_relaciones', 'historia.ver_estado', 'cita.ver_historial',
        'documento.ver_carpeta', 'documento.subir', 'documento.gestionar_propios',
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
     * Quita los permisos nuevos y las asignaciones de `citas.*` y `ciudadano.crear` que añadió.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::NUEVOS)->delete();

        $quitar = [
            'tramitacion' => ['citas.solicitar', 'citas.gestionar'],
            'consulta_basica' => ['ciudadano.crear'],
        ];
        foreach ($quitar as $rol => $permisos) {
            $rolId = DB::table('roles')->where('name', $rol)->value('id');
            if ($rolId === null) {
                continue;
            }
            DB::table('role_has_permissions')
                ->where('role_id', $rolId)
                ->whereIn('permission_id', DB::table('permissions')->whereIn('name', $permisos)->pluck('id'))
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
