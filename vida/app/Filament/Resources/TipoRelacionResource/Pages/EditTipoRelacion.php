<?php

namespace App\Filament\Resources\TipoRelacionResource\Pages;

use App\Filament\Resources\TipoRelacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoRelacion extends EditRecord
{
    protected static string $resource = TipoRelacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
