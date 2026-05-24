<?php

namespace App\Filament\Resources\UsuarioRolResource\Pages;

use App\Filament\Resources\UsuarioRolResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListUsuarioRoles extends ListRecords
{
    protected static string $resource = UsuarioRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
