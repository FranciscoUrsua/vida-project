<?php

namespace App\Filament\Resources\ParametroInformeResource\Pages;

use App\Filament\Resources\ParametroInformeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Página de solo lectura de parámetros de informe: la abre quien puede consultarlas pero no editarlas.
 */
class ViewParametroInforme extends ViewRecord
{
    protected static string $resource = ParametroInformeResource::class;

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
