<?php

namespace App\Filament\Resources\TipoDocumentalResource\Pages;

use App\Filament\Resources\TipoDocumentalResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de tipos documentales.
 */
class CreateTipoDocumental extends CreateRecord
{
    protected static string $resource = TipoDocumentalResource::class;
}
