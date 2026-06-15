<?php

namespace App\Filament\Resources\TipoFichaResource\Pages;

use App\Filament\Resources\TipoFichaResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Models\TipoFicha;

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
