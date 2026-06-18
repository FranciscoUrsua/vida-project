<?php

namespace App\Filament\Resources\RolResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\RolResource;
use Filament\Actions;

/**
 * Página de listado de roles.
 */
class ListRoles extends ListRecords
{
    protected static string $resource = RolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
