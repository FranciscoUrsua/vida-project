<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos del encargo profesional y del rol `consulta_profesional` (bloque C1 de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Crea `derivacion.ver_asignadas`, `derivacion.registrar_resultado` y
 * `ciudadano.leer_por_encargo`, se los da al rol y le retira lo que chocaba con el
 * diseño: `ciudadano.leer` (consulta libre de cualquier persona), `historia.leer`,
 * `apunte.*`, `plan.leer` y `documento.ver_carpeta`. Mismo resultado que `RolesSeeder`.
 */
return new class extends Migration
{
    /** @var array<string, string> Permisos nuevos con su descripción. */
    private const NUEVOS = [
        'derivacion.ver_asignadas' => 'Entrar en «Mis derivaciones»: los encargos profesionales asignados',
        'derivacion.registrar_resultado' => 'Recibir encargos profesionales, registrar su resultado y cerrarlos',
        'ciudadano.leer_por_encargo' => 'Ver en lectura la ficha de las personas de sus encargos profesionales',
    ];

    /** @var list<string> Permisos finales del rol. */
    private const DEL_ROL = [
        'ciudadano.ver_ficha', 'ciudadano.leer_por_encargo', 'derivacion.ver_asignadas',
        'derivacion.registrar_resultado', 'documento.subir',
    ];

    /** @var list<string> Permisos que tenía el rol antes. */
    private const ANTERIORES = [
        'ciudadano.ver_ficha', 'ciudadano.leer', 'historia.leer', 'apunte.crear', 'apunte.leer',
        'apunte.leer_propio', 'plan.leer', 'documento.ver_carpeta',
    ];

    /**
     * Crea los permisos y deja el rol con los suyos.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::NUEVOS as $nombre => $descripcion) {
            if (! DB::table('permissions')->where('name', $nombre)->where('guard_name', 'web')->exists()) {
                $datos = ['name' => $nombre, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()];
                // La columna de descripción existe si la creó la migración de permisos del proyecto
                if (DB::getSchemaBuilder()->hasColumn('permissions', 'descripcion')) {
                    $datos['descripcion'] = $descripcion;
                }
                DB::table('permissions')->insert($datos);
            }
        }

        $this->dejarRolCon(self::DEL_ROL);
    }

    /**
     * Devuelve al rol sus permisos anteriores (los permisos nuevos se quedan).
     *
     * @return void
     */
    public function down(): void
    {
        $this->dejarRolCon(self::ANTERIORES);
    }

    /**
     * Sustituye los permisos del rol por los indicados.
     *
     * @param list<string> $permisos
     *
     * @return void
     */
    private function dejarRolCon(array $permisos): void
    {
        $rolId = DB::table('roles')->where('name', 'consulta_profesional')->value('id');
        if ($rolId === null) {
            return;
        }

        DB::table('role_has_permissions')->where('role_id', $rolId)->delete();
        foreach (DB::table('permissions')->whereIn('name', $permisos)->pluck('id') as $permisoId) {
            DB::table('role_has_permissions')->insert(['role_id' => $rolId, 'permission_id' => $permisoId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
