<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrestaciones extends ListRecords
{
    protected static string $resource = PrestacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
