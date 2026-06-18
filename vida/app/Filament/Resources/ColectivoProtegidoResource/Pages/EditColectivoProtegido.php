<?php

namespace App\Filament\Resources\ColectivoProtegidoResource\Pages;

use App\Filament\Resources\ColectivoProtegidoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de colectivos protegidos.
 */
class EditColectivoProtegido extends EditRecord
{
    protected static string $resource = ColectivoProtegidoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
