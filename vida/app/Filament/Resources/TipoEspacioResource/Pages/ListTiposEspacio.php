<?php

namespace App\Filament\Resources\TipoEspacioResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoEspacioResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de tipos de espacio.
 */
class ListTiposEspacio extends ListRecords
{
    protected static string $resource = TipoEspacioResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
