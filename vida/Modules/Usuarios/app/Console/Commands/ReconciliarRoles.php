<?php

namespace Modules\Usuarios\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;

/**
 * Comando de reconciliación de roles.
 *
 * Sincroniza model_has_roles de Spatie con el estado actual de usuario_rol.
 * Debe ejecutarse al arrancar el sistema por primera vez y tras migraciones.
 *
 * Paso 1: para cada UsuarioRol vigente, garantiza que el rol está en model_has_roles.
 * Paso 2: elimina de model_has_roles cualquier rol que no tenga un UsuarioRol vigente.
 *
 * @see docs/modulo-usuarios-permisos.md sección 4.3
 */
class ReconciliarRoles extends Command
{
    /** @var string */
    protected $signature = 'usuarios:reconciliar-roles';

    /** @var string */
    protected $description = 'Sincroniza model_has_roles de Spatie con el estado actual de usuario_rol';

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle(): int
    {
        // Paso 1: asignar todos los roles vigentes
        $vigentes = UsuarioRol::vigentes()->with(['usuario', 'rol'])->get();

        foreach ($vigentes as $asignacion) {
            if ($asignacion->usuario && $asignacion->rol) {
                $asignacion->usuario->assignRole($asignacion->rol);
            }
        }

        // Paso 2: revocar roles huérfanos (están en Spatie pero no tienen UsuarioRol vigente)
        $registrosSpatie = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->get();

        foreach ($registrosSpatie as $registro) {
            $hayVigente = UsuarioRol::where('usuario_id', $registro->model_id)
                ->where('rol_id', $registro->role_id)
                ->vigentes()
                ->exists();

            if (! $hayVigente) {
                $user = User::find($registro->model_id);
                $rol  = Role::find($registro->role_id);
                if ($user && $rol) {
                    $user->removeRole($rol);
                }
            }
        }

        $this->info("Reconciliación completada: {$vigentes->count()} asignaciones vigentes procesadas.");

        return self::SUCCESS;
    }
}
