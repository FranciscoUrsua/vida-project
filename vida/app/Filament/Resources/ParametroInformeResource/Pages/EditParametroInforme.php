<?php

namespace App\Filament\Resources\ParametroInformeResource\Pages;

use App\Filament\Resources\ParametroInformeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de parámetros de informe.
 */
class EditParametroInforme extends EditRecord
{
    protected static string $resource = ParametroInformeResource::class;

    /**
     * Acción de borrar en la cabecera.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
