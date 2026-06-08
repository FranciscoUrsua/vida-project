<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de un centro.
 *
 * Añade un Action en la cabecera para gestionar las prestaciones del centro
 * mediante un SlideOver con selector interactivo. La selección de prestaciones
 * se gestiona en el componente Livewire SelectorPrestacionesCentro, no en
 * el formulario principal del centro.
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * Actions adicionales en la cabecera de la página de edición.
     * El botón de prestaciones abre un SlideOver con el selector Livewire.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestionarPrestaciones')
                ->label('Prestaciones del centro')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalHeading('Prestaciones del centro')
                ->modalDescription(
                    'Selecciona las prestaciones que ofrece este centro. ' .
                    'Los cambios se guardan al pulsar "Guardar selección".'
                )
                ->modalContent(
                    fn () => view(
                        'livewire.centros.selector-prestaciones-centro-modal',
                        ['centroId' => $this->record->id]
                    )
                )
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
}
