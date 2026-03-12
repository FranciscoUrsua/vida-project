<?php

namespace Modules\Usuarios\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Trait que añade al User la lógica de historial de roles.
 *
 * Los roles activos se consultan a través de Spatie (HasRoles).
 * Este trait añade la capacidad de consultar el HISTORIAL de roles
 * y provee métodos de conveniencia que unifican ambas fuentes.
 *
 * @see docs/modulo-usuarios-permisos.md sección 4.2
 */
trait TieneRoles
{
    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Todos los registros de historial de roles del usuario.
     *
     * @return HasMany<UsuarioRol>
     */
    public function historialRoles(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'user_id');
    }

    /**
     * Únicamente los registros de rol vigentes.
     *
     * @return HasMany<UsuarioRol>
     */
    public function rolesVigentes(): HasMany
    {
        return $this->historialRoles()->vigentes();
    }

    // -------------------------------------------------------------------------
    // Métodos de dominio
    // -------------------------------------------------------------------------

    /**
     * Comprueba si el usuario tiene activo el rol indicado
     * según el historial de VIDA (no solo Spatie).
     *
     * Útil cuando la sincronización de Spatie pudiera estar desactualizada,
     * o para consultas históricas.
     *
     * @param string $rol Nombre del rol, ej: 'intervencion'
     * @return bool
     */
    public function tieneRolVigente(string $rol): bool
    {
        return $this->rolesVigentes()
            ->where('rol', $rol)
            ->exists();
    }

    /**
     * Comprueba si el usuario tiene el permiso indicado
     * a través de alguno de sus roles vigentes en Spatie.
     *
     * Delega en el método can() de Spatie (HasRoles), que evalúa
     * los roles y permisos activos en model_has_roles.
     *
     * @param string $permiso Nombre del permiso, ej: 'historia.leer'
     * @return bool
     */
    public function tienePermiso(string $permiso): bool
    {
        return $this->can($permiso);
    }
}
