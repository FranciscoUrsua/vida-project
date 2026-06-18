<?php

namespace App\Filament\Resources\InformeResource\Pages;

use App\Filament\Resources\InformeResource;
use App\Filament\Resources\Pages\ListRecords;

/**
 * Página de listado de informes.
 */
class ListInformes extends ListRecords
{
    protected static string $resource = InformeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
