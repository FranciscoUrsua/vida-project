<?php

namespace App\Filament\Resources\TipoFichaResource\Pages;

use App\Filament\Resources\TipoFichaResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Página de edición de tipos de ficha.
 */
class EditTipoFicha extends EditRecord
{
    protected static string $resource = TipoFichaResource::class;

    protected function getHeaderActions(): array
    {
        /** @var TipoFicha $record */
        $record = $this->getRecord();

        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $record->tieneFichasAsociadas()),
        ];
    }

    /**
     * Convierte los bloques crudos del Builder al formato canónico del schema.
     * En Filament 5, dehydrateStateUsing en un Builder no propaga su valor
     * a $data automáticamente; la transformación debe hacerse aquí.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['schema'] = TipoFichaResource::convertirSchemaBlocks($data['schema'] ?? []);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Schema inválido')
                ->body(implode(' ', array_merge(...array_values($e->errors()))))
                ->persistent()
                ->send();

            $this->halt();
            throw new \LogicException('unreachable');
        }
    }
}
