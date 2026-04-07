<?php

namespace App\Filament\Resources\CuadranteMesResource\Pages;

use App\Filament\Resources\CuadranteMesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCuadranteMes extends EditRecord
{
    protected static string $resource = CuadranteMesResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
