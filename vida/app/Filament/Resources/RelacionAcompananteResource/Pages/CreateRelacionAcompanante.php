<?php

namespace App\Filament\Resources\RelacionAcompananteResource\Pages;

use App\Filament\Resources\RelacionAcompananteResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de relaciones de acompañante.
 */
class CreateRelacionAcompanante extends CreateRecord
{
    protected static string $resource = RelacionAcompananteResource::class;

    /**
     * Fija el grupo del catálogo.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['grupo' => RelacionAcompananteResource::GRUPO];
    }
}
