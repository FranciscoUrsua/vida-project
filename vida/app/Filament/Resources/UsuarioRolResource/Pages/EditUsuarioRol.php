<?php

namespace App\Filament\Resources\UsuarioRolResource\Pages;

use App\Filament\Resources\UsuarioRolResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de asignaciones de rol de usuario.
 */
class EditUsuarioRol extends EditRecord
{
    protected static string $resource = UsuarioRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
