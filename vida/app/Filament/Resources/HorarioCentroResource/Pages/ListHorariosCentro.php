<?php

namespace App\Filament\Resources\HorarioCentroResource\Pages;

use App\Filament\Resources\HorarioCentroResource;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Pages\ListRecords;

class ListHorariosCentro extends ListRecords
{
    protected static string $resource = HorarioCentroResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
