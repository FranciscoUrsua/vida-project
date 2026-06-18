<?php

namespace App\Filament\Resources\HorarioCentroResource\Pages;

use App\Filament\Resources\HorarioCentroResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

/**
 * Página de listado de horarios de centro.
 */
class ListHorariosCentro extends ListRecords
{
    protected static string $resource = HorarioCentroResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
