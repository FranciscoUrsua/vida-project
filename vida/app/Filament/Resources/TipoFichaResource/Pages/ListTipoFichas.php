<?php

namespace App\Filament\Resources\TipoFichaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoFichaResource;
use Filament\Actions\CreateAction;

class ListTipoFichas extends ListRecords
{
    protected static string $resource = TipoFichaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
