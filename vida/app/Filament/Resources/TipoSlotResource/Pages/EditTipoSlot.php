<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\TipoSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de tipo de slot.
 */
class EditTipoSlot extends EditRecord
{
    protected static string $resource = TipoSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
