<?php

namespace App\Filament\Resources\ExcepcionProfesionalResource\Pages;

use App\Filament\Resources\ExcepcionProfesionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExcepcionesProfesional extends ListRecords
{
    protected static string $resource = ExcepcionProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
