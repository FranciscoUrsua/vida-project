<?php

namespace App\Filament\Resources\TitulacionResource\Pages;

use App\Filament\Resources\TitulacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTitulaciones extends ListRecords
{
    protected static string $resource = TitulacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
