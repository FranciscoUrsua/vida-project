<?php

namespace App\Filament\Resources\PerfilHorarioProfesionalResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PerfilHorarioProfesionalResource;
use Filament\Actions\CreateAction;

class ListPerfilesHorarioProfesional extends ListRecords
{
    protected static string $resource = PerfilHorarioProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
