<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListEstilosInforme extends ListRecords
{
    protected static string $resource = EstiloInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
