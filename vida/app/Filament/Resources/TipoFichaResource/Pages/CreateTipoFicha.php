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
