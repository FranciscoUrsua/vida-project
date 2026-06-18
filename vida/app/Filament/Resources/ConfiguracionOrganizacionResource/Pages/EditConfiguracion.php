<?php

namespace App\Filament\Resources\ConfiguracionOrganizacionResource\Pages;

use App\Filament\Resources\ConfiguracionOrganizacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de configuraciones de organización.
 */
class EditConfiguracion extends EditRecord
{
    protected static string $resource = ConfiguracionOrganizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
