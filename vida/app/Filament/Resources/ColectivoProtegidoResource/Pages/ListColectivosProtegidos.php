<?php

namespace App\Filament\Resources\ColectivoProtegidoResource\Pages;

use App\Filament\Resources\ColectivoProtegidoResource;
use App\Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListColectivosProtegidos extends ListRecords
{
    protected static string $resource = ColectivoProtegidoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
