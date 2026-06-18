<?php

namespace App\Filament\Resources\ExcepcionProfesionalResource\Pages;

use App\Filament\Resources\ExcepcionProfesionalResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

/**
 * Página de listado de excepciones profesionales.
 */
class ListExcepcionesProfesional extends ListRecords
{
    protected static string $resource = ExcepcionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
