<?php

namespace App\Filament\Resources\TipoEscalaResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoEscalaResource;
use Filament\Actions\CreateAction;

class ListTipoEscalas extends ListRecords
{
    protected static string $resource = TipoEscalaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
