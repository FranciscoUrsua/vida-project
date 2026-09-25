<?php

namespace App\Filament\Resources\TipoDocumentalResource\Pages;

use App\Filament\Resources\TipoDocumentalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Documentos\Models\TipoDocumental;

/**
 * Página de edición de tipos documentales.
 */
class EditTipoDocumental extends EditRecord
{
    protected static string $resource = TipoDocumentalResource::class;

    /**
     * Acción de borrado, solo para tipos sin documentos.
     *
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        /** @var TipoDocumental $record */
        $record = $this->getRecord();

        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $record->documentos()->exists()),
        ];
    }
}
