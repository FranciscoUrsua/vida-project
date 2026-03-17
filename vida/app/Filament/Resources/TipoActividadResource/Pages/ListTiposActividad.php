<?php

namespace App\Filament\Resources\TipoActividadResource\Pages;

use App\Filament\Resources\TipoActividadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTiposActividad extends ListRecords
{
    protected static string $resource = TipoActividadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
