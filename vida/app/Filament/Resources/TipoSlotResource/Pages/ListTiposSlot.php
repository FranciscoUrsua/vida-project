<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\TipoSlotResource;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Pages\ListRecords;

class ListTiposSlot extends ListRecords
{
    protected static string $resource = TipoSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
