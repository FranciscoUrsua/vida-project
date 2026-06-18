<?php

namespace App\Filament\Resources\TipoPlanResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoPlanResource;
use Filament\Actions\CreateAction;

/** Página de listado de tipos de plan. */
class ListTipoPlanes extends ListRecords
{
    protected static string $resource = TipoPlanResource::class;

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
