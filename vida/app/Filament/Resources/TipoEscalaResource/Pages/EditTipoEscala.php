<?php

namespace App\Filament\Resources\TipoEscalaResource\Pages;

use App\Filament\Resources\TipoEscalaResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Página de edición de tipos de escala.
 */
class EditTipoEscala extends EditRecord
{
    protected static string $resource = TipoEscalaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['secciones_escala'] = $data['schema']['secciones'] ?? [];
        $data['rangos'] = $data['rangos_interpretacion']['rangos'] ?? [];
        $data['nota_interpretacion'] = $data['rangos_interpretacion']['nota_interpretacion'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['schema'] = ['secciones' => $data['secciones_escala'] ?? []];
        $data['rangos_interpretacion'] = [
            'rangos' => $data['rangos'] ?? [],
            'nota_interpretacion' => $data['nota_interpretacion'] ?? null,
        ];

        unset($data['secciones_escala'], $data['rangos'], $data['nota_interpretacion']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Error en los datos de la escala')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
            throw new \LogicException('unreachable');
        }
    }
}
