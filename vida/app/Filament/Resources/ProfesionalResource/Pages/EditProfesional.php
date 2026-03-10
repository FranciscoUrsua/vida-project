<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Resources\ProfesionalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProfesional extends EditRecord
{
    protected static string $resource = ProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
