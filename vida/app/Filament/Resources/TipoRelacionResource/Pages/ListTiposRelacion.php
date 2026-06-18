<?php

namespace App\Filament\Resources\TipoRelacionResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoRelacionResource;
use Filament\Actions;

/**
 * Página de listado de tipos de relación.
 */
class ListTiposRelacion extends ListRecords
{
    protected static string $resource = TipoRelacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
