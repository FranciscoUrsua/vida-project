<?php

namespace App\Filament\Resources\ExcepcionProfesionalResource\Pages;

use App\Filament\Resources\ExcepcionProfesionalResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Agenda\Enums\OrigenExcepcion;

/**
 * Página de creación de excepciones profesionales.
 */
class CreateExcepcionProfesional extends CreateRecord
{
    protected static string $resource = ExcepcionProfesionalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creado_por_id'] = auth()->id();
        $data['origen'] = OrigenExcepcion::Manual->value;

        return $data;
    }
}
