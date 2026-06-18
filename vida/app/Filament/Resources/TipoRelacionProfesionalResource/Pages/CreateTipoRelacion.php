<?php

namespace App\Filament\Resources\TipoRelacionProfesionalResource\Pages;

use App\Filament\Resources\TipoRelacionProfesionalResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Página de creación de tipos de relación profesional.
 */
class CreateTipoRelacion extends CreateRecord
{
    protected static string $resource = TipoRelacionProfesionalResource::class;
}
