<?php

namespace Modules\Usuarios\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Usuarios\Models\UsuarioRol;
use Spatie\Permission\Models\Role;

/**
 * Trait que añade al User la lógica de historial de roles.
 */
trait TieneRoles
{
    /**
     * @return HasMany<UsuarioRol, $this>
     */
    public function historialRoles(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'usuario_id');
    }

    /**
     * @return HasMany<UsuarioRol, $this>
     */
    public function rolesVigentes(): HasMany
    {
        return $this->historialRoles()->vigentes();
    }

    /**
     * @return HasMany<UsuarioRol, $this>
     */
    public function rolesPendientes(): HasMany
    {
        return $this->historialRoles()->pendientes();
    }

    public function tieneRolVigente(string $rolNombre): bool
    {
        $rol = Role::findByName($rolNombre);

        if (! $rol) {
            return false;
        }

        return $this->rolesVigentes()
            ->where('rol_id', $rol->id)
            ->exists();
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->can($permiso);
    }
}
