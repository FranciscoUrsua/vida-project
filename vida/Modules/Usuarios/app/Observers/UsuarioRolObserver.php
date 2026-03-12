<?php

namespace Modules\Usuarios\Observers;

use Illuminate\Support\Carbon;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Observer de UsuarioRol.
 *
 * Mantiene sincronizados el historial VIDA (tabla usuario_rol) y
 * la tabla model_has_roles de Spatie. Spatie es la fuente de verdad
 * para los roles ACTIVOS; usuario_rol es la fuente de verdad para
 * el HISTORIAL.
 *
 * Reglas de sincronización:
 * - Al crear un UsuarioRol con fecha_fin null → asignar rol en Spatie.
 * - Al actualizar un UsuarioRol con fecha_fin → revocar en Spatie
 *   si no hay otros UsuarioRol vigentes para ese rol.
 *
 * @see \Modules\Usuarios\Models\UsuarioRol
 */
class UsuarioRolObserver
{
    /**
     * Sincroniza Spatie al crear un nuevo registro de rol.
     *
     * Si el nuevo rol es vigente (fecha_fin null o futura), se asigna
     * en Spatie para que los permisos queden activos inmediatamente.
     *
     * @param UsuarioRol $usuarioRol
     * @return void
     */
    public function created(UsuarioRol $usuarioRol): void
    {
        $esVigente = $usuarioRol->fecha_fin === null
            || Carbon::parse($usuarioRol->fecha_fin)->isFuture()
            || Carbon::parse($usuarioRol->fecha_fin)->isToday();

        if ($esVigente) {
            $usuarioRol->usuario->assignRole($usuarioRol->rol);
        }
    }

    /**
     * Sincroniza Spatie al actualizar un registro de rol.
     *
     * Si se ha establecido fecha_fin (el rol ha caducado), comprueba si
     * hay algún otro registro vigente para ese rol. Si no lo hay, revoca
     * el rol en Spatie.
     *
     * @param UsuarioRol $usuarioRol
     * @return void
     */
    public function updated(UsuarioRol $usuarioRol): void
    {
        $seFinalizo = $usuarioRol->wasChanged('fecha_fin')
            && $usuarioRol->fecha_fin !== null
            && Carbon::parse($usuarioRol->fecha_fin)->isPast();

        if (! $seFinalizo) {
            return;
        }

        // ¿Hay otro UsuarioRol vigente para este rol en este usuario?
        $hayOtroVigente = UsuarioRol::where('user_id', $usuarioRol->user_id)
            ->where('rol', $usuarioRol->rol)
            ->where('id', '!=', $usuarioRol->id)
            ->vigentes()
            ->exists();

        if (! $hayOtroVigente) {
            $usuarioRol->usuario->removeRole($usuarioRol->rol);
        }
    }
}
