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

    /**
     * Acciones de cabecera.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * Guardar el perfil es verificarlo: deja de ser «horario no personalizado».
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['pendiente_verificar'] = false;

        return $data;
    }
}
