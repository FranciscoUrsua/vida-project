<?php

namespace App\Filament\Resources\DistritoResource\Pages;

use App\Filament\Resources\DistritoResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDistritos extends ListRecords
{
    protected static string $resource = DistritoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
