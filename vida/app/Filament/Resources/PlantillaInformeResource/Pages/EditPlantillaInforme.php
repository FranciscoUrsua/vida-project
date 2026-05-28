<?php

namespace App\Filament\Resources\PlantillaInformeResource\Pages;

use App\Filament\Resources\PlantillaInformeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlantillaInforme extends EditRecord
{
    protected static string $resource = PlantillaInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

}
