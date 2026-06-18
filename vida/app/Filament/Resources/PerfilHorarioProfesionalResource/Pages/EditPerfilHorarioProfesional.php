<?php

namespace App\Filament\Resources\PerfilHorarioProfesionalResource\Pages;

use App\Filament\Resources\PerfilHorarioProfesionalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de perfiles horarios profesionales.
 */
class EditPerfilHorarioProfesional extends EditRecord
{
    protected static string $resource = PerfilHorarioProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
