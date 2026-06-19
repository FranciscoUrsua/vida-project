<?php

namespace App\Filament\Resources\TipoRelacionProfesionalResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoRelacionProfesionalResource;
use Filament\Actions;

/**
 * Página de listado de tipos de relación profesional.
 */
/**
 * Página de listado de tipos de relación profesional.
 */
class ListTiposRelacion extends ListRecords
{
    protected static string $resource = TipoRelacionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
