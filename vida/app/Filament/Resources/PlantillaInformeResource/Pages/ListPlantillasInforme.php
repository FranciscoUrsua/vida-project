<?php

namespace App\Filament\Resources\PlantillaInformeResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PlantillaInformeResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de plantillas de informe.
 */
class ListPlantillasInforme extends ListRecords
{
    protected static string $resource = PlantillaInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
