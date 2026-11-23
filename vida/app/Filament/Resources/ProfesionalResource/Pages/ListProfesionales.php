<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Concerns\AvisaDireccionDelCentro;
use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ProfesionalResource;
use Filament\Actions;

/**
 * Página de listado de profesionales.
 */
class ListProfesionales extends ListRecords
{
    // R3: botón «Avisar a la dirección del centro» de una desactivación bloqueada
    use AvisaDireccionDelCentro;

    protected static string $resource = ProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
