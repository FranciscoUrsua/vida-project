<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

/**
 * Página de listado de estilos de informe.
 */
class ListEstilosInforme extends ListRecords
{
    protected static string $resource = EstiloInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
