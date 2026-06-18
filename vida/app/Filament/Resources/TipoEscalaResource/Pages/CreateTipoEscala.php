<?php

namespace App\Filament\Resources\TipoEscalaResource\Pages;

use App\Filament\Resources\TipoEscalaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Página de creación de tipos de escala.
 */
class CreateTipoEscala extends CreateRecord
{
    protected static string $resource = TipoEscalaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['schema'] = ['secciones' => $data['secciones_escala'] ?? []];
        $data['rangos_interpretacion'] = [
            'rangos' => $data['rangos'] ?? [],
            'nota_interpretacion' => $data['nota_interpretacion'] ?? null,
        ];

        unset($data['secciones_escala'], $data['rangos'], $data['nota_interpretacion']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
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
