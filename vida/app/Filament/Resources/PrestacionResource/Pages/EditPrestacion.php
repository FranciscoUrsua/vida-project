<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrestacion extends EditRecord
{
    protected static string $resource = PrestacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
