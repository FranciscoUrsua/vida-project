<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListCentros extends ListRecords
{
    protected static string $resource = CentroResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
