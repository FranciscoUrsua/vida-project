<?php

namespace App\Filament\Resources\ExcepcionProfesionalResource\Pages;

use App\Filament\Resources\ExcepcionProfesionalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExcepcionProfesional extends EditRecord
{
    protected static string $resource = ExcepcionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
