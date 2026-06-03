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
 * - Al crear un UsuarioRol con estado=activo y fecha vigente → assignRole.
 * - Al aprobar una asignación (estado: pendiente_aprobacion → activo) → assignRole.
 * - Al revocar (estado → inactivo) o caducar (fecha_fin en el pasado) → removeRole
 *   si no hay otro UsuarioRol vigente para ese rol en ese usuario.
 *
 * @see UsuarioRol
 * @see docs/modulo-usuarios-permisos.md sección 4.3
 */
class UsuarioRolObserver
{
    /**
     * Sincroniza Spatie al crear un nuevo registro de rol.
     *
     * Solo sincroniza si el estado es activo y la fecha es vigente.
     * Los registros pendientes de aprobación NO se sincronizan.
     */
    public function created(UsuarioRol $usuarioRol): void
    {
        if ($usuarioRol->estado !== 'activo') {
            return;
        }

        if ($this->esVigente($usuarioRol)) {
            $usuarioRol->usuario->assignRole($usuarioRol->rol);
        }
    }

    /**
     * Sincroniza Spatie al actualizar un registro de rol.
     *
     * Gestiona tres casos:
     * 1. Aprobación: estado pendiente_aprobacion → activo → assignRole.
     * 2. Caducidad: fecha_fin establecida en el pasado → removeRole si no hay otro vigente.
     * 3. Revocación: estado → inactivo → removeRole si no hay otro vigente.
     */
    public function updated(UsuarioRol $usuarioRol): void
    {
        $estadoCambio = $usuarioRol->wasChanged('estado');
        $fechaCambio = $usuarioRol->wasChanged('fecha_fin');

        // Caso 1: acaba de ser aprobada
        if ($estadoCambio && $usuarioRol->estado === 'activo') {
            if ($this->esVigente($usuarioRol)) {
                $usuarioRol->usuario->assignRole($usuarioRol->rol);
            }

            return;
        }

        // Caso 2 y 3: revocación o caducidad
        $seRevoco = $estadoCambio && $usuarioRol->estado === 'inactivo';
        $seCaduco = $fechaCambio
            && $usuarioRol->fecha_fin !== null
            && Carbon::parse($usuarioRol->fecha_fin)->isPast();

        if (! $seRevoco && ! $seCaduco) {
            return;
        }

        $hayOtroVigente = UsuarioRol::where('usuario_id', $usuarioRol->usuario_id)
            ->where('rol_id', $usuarioRol->rol_id)
            ->where('id', '!=', $usuarioRol->id)
            ->vigentes()
            ->exists();

        if (! $hayOtroVigente) {
            $usuarioRol->usuario->removeRole($usuarioRol->rol);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Comprueba si la fecha de vigencia del registro es válida (hoy o futura).
     */
    private function esVigente(UsuarioRol $usuarioRol): bool
    {
        return $usuarioRol->fecha_fin === null
            || Carbon::parse($usuarioRol->fecha_fin)->isFuture()
            || Carbon::parse($usuarioRol->fecha_fin)->isToday();
    }
}
