<?php

namespace App\Filament\Resources\TitulacionResource\Pages;

use App\Filament\Resources\TitulacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTitulacion extends EditRecord
{
    protected static string $resource = TitulacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
