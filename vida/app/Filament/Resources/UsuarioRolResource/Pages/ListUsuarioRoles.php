<?php

namespace App\Filament\Resources\UsuarioRolResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\UsuarioRolResource;

/**
 * Página de listado de asignaciones de roles de usuario.
 */
class ListUsuarioRoles extends ListRecords
{
    protected static string $resource = UsuarioRolResource::class;
}
