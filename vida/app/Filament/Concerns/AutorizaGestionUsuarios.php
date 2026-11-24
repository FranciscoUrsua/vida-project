<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Autorización de los Resources de gestión de usuarios: asignaciones de rol,
 * perfiles horarios, excepciones y cuadrantes de los profesionales.
 *
 * Abiertos a `adm_sistema` y a `adm_usuarios`, que gestiona usuarios,
 * adscripciones y agendas (docs/modulo-usuarios-permisos.md §2, rol 3).
 * El resto del backoffice usa {@see AutorizaGestion} (solo `adm_sistema`).
 */
trait AutorizaGestionUsuarios
{
    /**
     * Indica si el usuario puede ver cualquier recurso de gestión.
     *
     * @return bool
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede crear recursos de gestión.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede editar un recurso de gestión.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }

    /**
     * Indica si el usuario puede eliminar un recurso de gestión.
     *
     * @param Model $record Registro objetivo.
     *
     * @return bool
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['adm_sistema', 'adm_usuarios']) ?? false;
    }
}
