<?php

namespace App\Filament\Resources\UnidadOrganizativaResource\Pages;

use App\Filament\Resources\UnidadOrganizativaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de unidades organizativas.
 */
/**
 * Página de edición de unidades organizativas.
 */
class EditUnidadOrganizativa extends EditRecord
{
    protected static string $resource = UnidadOrganizativaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
