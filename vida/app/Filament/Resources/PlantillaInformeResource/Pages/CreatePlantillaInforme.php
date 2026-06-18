<?php

namespace App\Filament\Resources\PlantillaInformeResource\Pages;

use App\Filament\Resources\PlantillaInformeResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de plantillas de informe.
 */
class CreatePlantillaInforme extends CreateRecord
{
    protected static string $resource = PlantillaInformeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creada_por'] = auth()->id();

        // Normaliza el campo secciones: si viene como string JSON, lo decodifica
        if (is_string($data['secciones'])) {
            $data['secciones'] = json_decode($data['secciones'], true) ?? [];
        }

        return $data;
    }
}
