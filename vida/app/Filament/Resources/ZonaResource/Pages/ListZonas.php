<?php

namespace App\Filament\Resources\ZonaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ZonaResource;
use Filament\Actions;

/**
 * Página de listado de zonas.
 */
class ListZonas extends ListRecords
{
    protected static string $resource = ZonaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
