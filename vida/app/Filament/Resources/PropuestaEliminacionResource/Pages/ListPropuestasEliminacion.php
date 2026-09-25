<?php

namespace App\Filament\Resources\PropuestaEliminacionResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PropuestaEliminacionResource;

/**
 * Página de listado de propuestas de eliminación de documentos.
 */
class ListPropuestasEliminacion extends ListRecords
{
    protected static string $resource = PropuestaEliminacionResource::class;
}
