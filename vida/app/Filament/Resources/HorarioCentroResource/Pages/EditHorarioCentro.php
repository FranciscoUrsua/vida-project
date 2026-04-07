<?php

namespace App\Filament\Resources\HorarioCentroResource\Pages;

use App\Filament\Resources\HorarioCentroResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHorarioCentro extends EditRecord
{
    protected static string $resource = HorarioCentroResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
