<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de un centro. Las prestaciones no se editan aquí: son las
 * del catálogo de su tipo (docs/modulo-tipo-centro.md §6.1).
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;
}
