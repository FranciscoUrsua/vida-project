<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoSlotResource;
use Filament\Actions;

/**
 * Página de listado de tipos de slot.
 */
class ListTiposSlot extends ListRecords
{
    protected static string $resource = TipoSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
