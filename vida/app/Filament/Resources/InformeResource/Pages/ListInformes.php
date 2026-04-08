<?php

namespace App\Filament\Resources\InformeResource\Pages;

use App\Filament\Resources\InformeResource;
use Filament\Resources\Pages\ListRecords;

class ListInformes extends ListRecords
{
    protected static string $resource = InformeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
