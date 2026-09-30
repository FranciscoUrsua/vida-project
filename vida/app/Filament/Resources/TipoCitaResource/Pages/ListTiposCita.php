<?php

namespace App\Filament\Resources\TipoCitaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoCitaResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de tipos de cita.
 */
class ListTiposCita extends ListRecords
{
    protected static string $resource = TipoCitaResource::class;

    /**
     * Acción de cabecera: crear tipo de cita.
     *
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
