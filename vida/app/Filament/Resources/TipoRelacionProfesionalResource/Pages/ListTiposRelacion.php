<?php

namespace App\Filament\Resources\TipoRelacionProfesionalResource\Pages;

use App\Filament\Resources\TipoRelacionProfesionalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTiposRelacion extends ListRecords
{
    protected static string $resource = TipoRelacionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
