<?php

namespace App\Filament\Resources\EstiloInformeResource\Pages;

use App\Filament\Resources\EstiloInformeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEstiloInforme extends EditRecord
{
    protected static string $resource = EstiloInformeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
