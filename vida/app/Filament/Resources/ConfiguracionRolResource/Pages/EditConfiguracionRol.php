<?php

namespace App\Filament\Resources\ConfiguracionRolResource\Pages;

use App\Filament\Resources\ConfiguracionRolResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de configuraciones de rol.
 */
class EditConfiguracionRol extends EditRecord
{
    protected static string $resource = ConfiguracionRolResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
