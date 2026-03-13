<?php

namespace App\Filament\Resources\TipoRelacionProfesionalResource\Pages;

use App\Filament\Resources\TipoRelacionProfesionalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoRelacion extends EditRecord
{
    protected static string $resource = TipoRelacionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
