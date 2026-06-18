<?php

namespace App\Filament\Resources\UsuarioResource\Pages;

use App\Filament\Resources\UsuarioResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de usuarios.
 */
class CreateUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;
}
