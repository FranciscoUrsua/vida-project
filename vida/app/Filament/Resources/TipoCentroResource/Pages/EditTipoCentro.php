<?php

namespace App\Filament\Resources\TipoCentroResource\Pages;

use App\Filament\Resources\TipoCentroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoCentro extends EditRecord
{
    protected static string $resource = TipoCentroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
