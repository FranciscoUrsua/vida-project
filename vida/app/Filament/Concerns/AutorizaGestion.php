<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Autorización estándar para Resources de backoffice.
 *
 * Restringe el acceso a los roles con capacidad de gestión
 * del sistema: adm_sistema y adm_usuarios.
 */
trait AutorizaGestion
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
