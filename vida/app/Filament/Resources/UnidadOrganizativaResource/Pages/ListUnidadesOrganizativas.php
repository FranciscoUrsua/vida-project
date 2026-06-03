<?php

namespace App\Filament\Resources\UnidadOrganizativaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\UnidadOrganizativaResource;
use Filament\Actions;

class ListUnidadesOrganizativas extends ListRecords
{
    protected static string $resource = UnidadOrganizativaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
