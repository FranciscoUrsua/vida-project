<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Modules\Centro\Models\Centro;

/**
 * Página de edición de un centro.
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var Centro $centro */
        $centro = $this->record;

        return [
            Action::make('gestionarPrestaciones')
                ->label('Prestaciones del centro')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalHeading('Prestaciones del centro')
                ->modalDescription(
                    'Selecciona las prestaciones que ofrece este centro. '
                    .'Los cambios se guardan al pulsar "Guardar selección".'
                )
                ->modalContent(
                    fn () => view(
                        'livewire.centros.selector-prestaciones-centro-modal',
                        ['centroId' => $centro->id]
                    )
                )
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
}
