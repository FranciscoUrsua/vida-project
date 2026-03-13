<?php

namespace App\Filament\Resources\UsuarioRolResource\Pages;

use App\Filament\Resources\UsuarioRolResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUsuarioRol extends EditRecord
{
    protected static string $resource = UsuarioRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
