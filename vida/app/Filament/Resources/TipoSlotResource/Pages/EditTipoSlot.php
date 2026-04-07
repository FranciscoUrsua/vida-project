<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\TipoSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoSlot extends EditRecord
{
    protected static string $resource = TipoSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
