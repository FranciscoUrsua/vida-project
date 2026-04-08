<?php

namespace App\Filament\Resources\PlantillaInformeResource\Pages;

use App\Filament\Resources\PlantillaInformeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlantillasInforme extends ListRecords
{
    protected static string $resource = PlantillaInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
