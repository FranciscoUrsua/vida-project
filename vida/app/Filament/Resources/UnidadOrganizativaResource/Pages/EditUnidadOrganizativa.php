<?php

namespace App\Filament\Resources\UnidadOrganizativaResource\Pages;

use App\Filament\Resources\UnidadOrganizativaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnidadOrganizativa extends EditRecord
{
    protected static string $resource = UnidadOrganizativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
