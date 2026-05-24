<?php

namespace App\Filament\Resources\ColectivoProtegidoResource\Pages;

use App\Filament\Resources\ColectivoProtegidoResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ListRecords;

class ListColectivosProtegidos extends ListRecords
{
    protected static string $resource = ColectivoProtegidoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
