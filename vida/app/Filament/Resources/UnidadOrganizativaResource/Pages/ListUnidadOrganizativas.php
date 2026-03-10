<?php

namespace App\Filament\Resources\UnidadOrganizativaResource\Pages;

use App\Filament\Resources\UnidadOrganizativaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnidadOrganizativas extends ListRecords
{
    protected static string $resource = UnidadOrganizativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
