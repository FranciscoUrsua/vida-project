<?php

namespace App\Filament\Resources\PlantillaInformeResource\Pages;

use App\Filament\Resources\PlantillaInformeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Página de solo lectura de plantillas de informe: la abre quien puede consultarlas pero no editarlas.
 */
class ViewPlantillaInforme extends ViewRecord
{
    protected static string $resource = PlantillaInformeResource::class;

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
