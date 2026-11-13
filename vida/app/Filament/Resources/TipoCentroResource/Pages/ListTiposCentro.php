<?php

namespace App\Filament\Resources\TipoCentroResource\Pages;

use App\Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TipoCentroResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;

/** Página de listado de tipos de centro. */
class ListTiposCentro extends ListRecords
{
    protected static string $resource = TipoCentroResource::class;

    /**
     * Alta de tipo de centro.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
