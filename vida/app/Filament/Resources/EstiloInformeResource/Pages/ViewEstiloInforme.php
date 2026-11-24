<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Página de solo lectura de estilos de informe: la abre quien puede consultarlas pero no editarlas.
 */
class ViewEstiloInforme extends ViewRecord
{
    protected static string $resource = EstiloInformeResource::class;

    /**
     * «Editar», solo si el usuario puede editar este registro.
     *
     * @return array<int, EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
