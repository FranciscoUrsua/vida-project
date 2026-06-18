<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PrestacionResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de prestaciones.
 */
class ListPrestaciones extends ListRecords
{
    protected static string $resource = PrestacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
