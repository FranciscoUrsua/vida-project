<?php

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Resources\SalaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de sala.
 */
class EditSala extends EditRecord
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
