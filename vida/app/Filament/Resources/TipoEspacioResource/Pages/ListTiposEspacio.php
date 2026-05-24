<?php

namespace App\Filament\Resources\TipoEspacioResource\Pages;

use App\Filament\Resources\TipoEspacioResource;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Pages\ListRecords;

class ListTiposEspacio extends ListRecords
{
    protected static string $resource = TipoEspacioResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
