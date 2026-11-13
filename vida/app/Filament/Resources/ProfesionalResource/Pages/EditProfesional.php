<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Acciones\DesactivarAction;
use App\Filament\Resources\ProfesionalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de profesionales.
 */
class EditProfesional extends EditRecord
{
    protected static string $resource = ProfesionalResource::class;

    /**
     * Acción de cabecera: desactivar, que sustituye al borrado (RN-20).
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [DesactivarAction::profesional()];
    }
}
