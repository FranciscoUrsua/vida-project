<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Autorización estándar para los Resources de configuración del backoffice.
 *
 * Solo `adm_sistema` configura la aplicación (catálogos, centros, prestaciones,
 * tipos, colectivos protegidos, organización). `adm_usuarios` gestiona usuarios,
 * adscripciones y agendas, y «no tiene acceso a la configuración»
 * (docs/modulo-usuarios-permisos.md §2, rol 3): sus recursos usan
 * {@see AutorizaGestionUsuarios}. Hasta el 25 nov 2026 este trait admitía también
 * `adm_usuarios`, y con ello le daba casi todo el backoffice.
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
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }

    /**
     * Indica si el usuario puede crear recursos de gestión.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('adm_sistema') ?? false;
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
        return auth()->user()?->hasRole('adm_sistema') ?? false;
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
        return auth()->user()?->hasRole('adm_sistema') ?? false;
    }
}
