<?php

namespace App\Filament\Resources\ConfiguracionRolResource\Pages;

use App\Filament\Resources\ConfiguracionRolResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;

/**
 * Página de listado de configuraciones de rol.
 */
class ListConfiguracionRol extends ListRecords
{
    protected static string $resource = ConfiguracionRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
