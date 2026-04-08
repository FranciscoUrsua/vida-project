<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEstiloInforme extends CreateRecord
{
    protected static string $resource = EstiloInformeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creado_por'] = auth()->id();
        return $data;
    }
}
