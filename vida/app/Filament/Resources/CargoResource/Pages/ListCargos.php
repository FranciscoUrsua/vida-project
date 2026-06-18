<?php

namespace App\Filament\Resources\CargoResource\Pages;

use App\Filament\Resources\CargoResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;

/**
 * Página de listado de cargos.
 */
class ListCargos extends ListRecords
{
    protected static string $resource = CargoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
