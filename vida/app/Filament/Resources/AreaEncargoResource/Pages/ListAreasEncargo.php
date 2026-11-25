<?php

namespace App\Filament\Resources\AreaEncargoResource\Pages;

use App\Filament\Resources\AreaEncargoResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

/** Página de listado de áreas de encargo. */
class ListAreasEncargo extends ListRecords
{
    protected static string $resource = AreaEncargoResource::class;

    /**
     * Botón de creación en la cabecera.
     *
     * @return array<mixed>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
