<?php

namespace App\Filament\Resources\ServicioEmergenciaResource\Pages;

use App\Filament\Resources\ServicioEmergenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de servicios de emergencia.
 */
class EditServicioEmergencia extends EditRecord
{
    protected static string $resource = ServicioEmergenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
