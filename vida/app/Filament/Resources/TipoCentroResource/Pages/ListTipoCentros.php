<?php

namespace App\Filament\Resources\TipoCentroResource\Pages;

use App\Filament\Resources\TipoCentroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoCentros extends ListRecords
{
    protected static string $resource = TipoCentroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
