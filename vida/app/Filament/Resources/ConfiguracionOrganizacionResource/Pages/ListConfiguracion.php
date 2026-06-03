<?php

namespace App\Filament\Resources\ConfiguracionOrganizacionResource\Pages;

use App\Filament\Resources\ConfiguracionOrganizacionResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListConfiguracion extends ListRecords
{
    protected static string $resource = ConfiguracionOrganizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
