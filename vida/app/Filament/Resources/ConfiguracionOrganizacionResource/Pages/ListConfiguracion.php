<?php

namespace App\Filament\Resources\ConfiguracionOrganizacionResource\Pages;

use App\Filament\Resources\ConfiguracionOrganizacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracion extends ListRecords
{
    protected static string $resource = ConfiguracionOrganizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
