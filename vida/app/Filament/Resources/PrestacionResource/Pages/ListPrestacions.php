<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrestacions extends ListRecords
{
    protected static string $resource = PrestacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
