<?php

namespace App\Filament\Resources\ProfesionalResource\Pages;

use App\Filament\Resources\ProfesionalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfesionals extends ListRecords
{
    protected static string $resource = ProfesionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
