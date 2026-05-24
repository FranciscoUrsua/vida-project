<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords as BaseListRecords;

/**
 * Clase base para páginas de listado de Resources.
 *
 * Filament v5 deja authorizeAccess() vacío en ListRecords, lo que permite
 * acceder por URL directa aunque canViewAny() devuelva false y el item de
 * navegación esté oculto. Este override cierra esa brecha.
 */
abstract class ListRecords extends BaseListRecords
{
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }
}
