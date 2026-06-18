<?php

namespace App\Filament\Resources\LogAlertasResource\Pages;

use App\Filament\Resources\LogAlertasResource;
use App\Filament\Resources\Pages\ListRecords;

/**
 * Página de listado de logs de alertas.
 */
class ListLogAlertas extends ListRecords
{
    protected static string $resource = LogAlertasResource::class;
}
