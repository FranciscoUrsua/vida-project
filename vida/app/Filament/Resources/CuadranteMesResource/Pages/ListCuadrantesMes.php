<?php

namespace App\Filament\Resources\CuadranteMesResource\Pages;

use App\Filament\Resources\CuadranteMesResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListCuadrantesMes extends ListRecords
{
    protected static string $resource = CuadranteMesResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
