<?php

namespace App\Filament\Resources\CuadranteMesResource\Pages;

use App\Filament\Resources\CuadranteMesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCuadrantesMes extends ListRecords
{
    protected static string $resource = CuadranteMesResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
