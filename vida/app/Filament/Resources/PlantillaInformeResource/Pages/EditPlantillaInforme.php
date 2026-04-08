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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Serializa secciones para el textarea JSON
        if (is_array($data['secciones'])) {
            $data['secciones'] = json_encode($data['secciones'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_string($data['secciones'])) {
            $data['secciones'] = json_decode($data['secciones'], true) ?? [];
        }
        return $data;
    }
}
