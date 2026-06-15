<?php

namespace App\Filament\Resources\TipoFichaResource\Pages;

use App\Filament\Resources\TipoFichaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTipoFicha extends CreateRecord
{
    protected static string $resource = TipoFichaResource::class;

    /**
     * Convierte los bloques crudos del Builder al formato canónico del schema.
     * En Filament 5, dehydrateStateUsing en un Builder no propaga su valor
     * a $data automáticamente; la transformación debe hacerse aquí.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['schema'] = TipoFichaResource::convertirSchemaBlocks($data['schema'] ?? []);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
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
