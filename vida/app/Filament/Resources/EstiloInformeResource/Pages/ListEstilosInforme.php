<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Pages\ListRecords;

class ListEstilosInforme extends ListRecords
{
    protected static string $resource = EstiloInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
