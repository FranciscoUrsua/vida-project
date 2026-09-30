<?php

namespace App\Filament\Resources\RelacionAcompananteResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\RelacionAcompananteResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de relaciones de acompañante.
 */
class ListRelacionesAcompanante extends ListRecords
{
    protected static string $resource = RelacionAcompananteResource::class;

    /**
     * Acción de cabecera: añadir relación.
     *
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
