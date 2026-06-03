<?php

namespace App\Filament\Resources\ServicioEmergenciaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ServicioEmergenciaResource;
use Filament\Actions;

class ListServiciosEmergencia extends ListRecords
{
    protected static string $resource = ServicioEmergenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
