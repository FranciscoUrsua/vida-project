<?php

namespace App\Filament\Resources\ParametroInformeResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ParametroInformeResource;
use Filament\Actions\CreateAction;

/**
 * Página de listado de parámetros de informe.
 */
class ListParametrosInforme extends ListRecords
{
    protected static string $resource = ParametroInformeResource::class;

    /**
     * Acción de crear (solo si el usuario puede).
     *
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
