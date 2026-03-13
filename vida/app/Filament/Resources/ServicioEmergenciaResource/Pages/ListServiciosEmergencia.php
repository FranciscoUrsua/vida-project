<?php

namespace App\Filament\Resources\ServicioEmergenciaResource\Pages;

use App\Filament\Resources\ServicioEmergenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiciosEmergencia extends ListRecords
{
    protected static string $resource = ServicioEmergenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
