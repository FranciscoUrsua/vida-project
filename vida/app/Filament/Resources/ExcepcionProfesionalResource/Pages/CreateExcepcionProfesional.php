<?php

namespace App\Filament\Resources\ExcepcionProfesionalResource\Pages;

use App\Filament\Resources\ExcepcionProfesionalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExcepcionProfesional extends CreateRecord
{
    protected static string $resource = ExcepcionProfesionalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creado_por_id'] = auth()->id();
        $data['origen'] = \Modules\Agenda\Enums\OrigenExcepcion::Manual->value;

        return $data;
    }
}
