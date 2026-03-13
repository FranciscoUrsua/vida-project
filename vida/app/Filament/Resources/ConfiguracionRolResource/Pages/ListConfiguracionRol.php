<?php

namespace App\Filament\Resources\ConfiguracionRolResource\Pages;

use App\Filament\Resources\ConfiguracionRolResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracionRol extends ListRecords
{
    protected static string $resource = ConfiguracionRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
