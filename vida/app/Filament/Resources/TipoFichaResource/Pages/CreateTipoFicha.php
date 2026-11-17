<?php

namespace App\Filament\Resources\TipoFichaResource\Pages;

use App\Filament\Resources\TipoFichaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Página de creación de tipos de ficha.
 */
class CreateTipoFicha extends CreateRecord
{
    protected static string $resource = TipoFichaResource::class;

    /**
     * Convierte los bloques crudos del Builder al schema canónico. Es la única
     * conversión: el Builder no tiene dehydrateStateUsing (ver TipoFichaResource).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
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
