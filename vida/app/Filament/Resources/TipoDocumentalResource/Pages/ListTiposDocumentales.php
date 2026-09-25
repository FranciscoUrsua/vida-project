<?php

namespace App\Filament\Resources\TipoDocumentalResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoDocumentalResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de tipos documentales.
 */
class ListTiposDocumentales extends ListRecords
{
    protected static string $resource = TipoDocumentalResource::class;

    /**
     * Acción de cabecera: crear tipo documental.
     *
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
