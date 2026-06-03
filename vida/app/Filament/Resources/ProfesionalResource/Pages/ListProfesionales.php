<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ProfesionalResource;
use Filament\Actions;

class ListProfesionales extends ListRecords
{
    protected static string $resource = ProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
