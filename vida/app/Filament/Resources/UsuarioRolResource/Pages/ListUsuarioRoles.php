<?php

namespace App\Filament\Resources\UsuarioRolResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\UsuarioRolResource;
use Filament\Actions;

class ListUsuarioRoles extends ListRecords
{
    protected static string $resource = UsuarioRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
