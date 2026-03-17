<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCentros extends ListRecords
{
    protected static string $resource = CentroResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
