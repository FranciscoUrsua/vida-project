<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de las fichas de la persona (SD-10 de la ficha sociodemográfica): los crea y los
 * añade a los roles, sin quitar nada (mismo resultado que `PermisosSeeder` y `RolesSeeder`).
 *
 * - `ciudadano.leer_fichas_persona`: intervención, supervisión y consulta básica.
 * - `ciudadano.registrar_fichas_persona`: intervención y consulta básica (se recoge en la acogida).
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque C
 */
return new class extends Migration
{
    /** @var array<string, string> Permisos nuevos con su descripción. */
    private const NUEVOS = [
        'ciudadano.leer_fichas_persona' => 'Ver las fichas de la persona (datos sociodemográficos y similares) en su ficha',
        'ciudadano.registrar_fichas_persona' => 'Registrar, actualizar y corregir las fichas de la persona (datos sociodemográficos y similares)',
    ];

    /** @var array<string, list<string>> Permisos que recibe cada rol. */
    private const POR_ROL = [
        'intervencion' => ['ciudadano.leer_fichas_persona', 'ciudadano.registrar_fichas_persona'],
        'supervision' => ['ciudadano.leer_fichas_persona'],
        'consulta_basica' => ['ciudadano.leer_fichas_persona', 'ciudadano.registrar_fichas_persona'],
    ];

    /**
     * Crea los permisos y los asigna.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::NUEVOS as $nombre => $descripcion) {
            if (! DB::table('permissions')->where('name', $nombre)->where('guard_name', 'web')->exists()) {
                $datos = ['name' => $nombre, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                if (DB::getSchemaBuilder()->hasColumn('permissions', 'descripcion')) {
                    $datos['descripcion'] = $descripcion;
                }
                DB::table('permissions')->insert($datos);
            }
        }

        foreach (self::POR_ROL as $rol => $permisos) {
            $rolId = DB::table('roles')->where('name', $rol)->where('guard_name', 'web')->value('id');
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
     * Quita los permisos de los roles y los borra.
     *
     * @return void
     */
    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::NUEVOS))->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
