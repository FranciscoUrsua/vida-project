<?php

namespace App\Filament\Resources\TipoSlotResource\Pages;

use App\Filament\Resources\TipoSlotResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Centro\Models\Centro;

/**
 * Página de creación de tipo de slot.
 *
 * Infiere automáticamente el horario_centro_id del centro del supervisor autenticado.
 */
class CreateTipoSlot extends CreateRecord
{
    protected static string $resource = TipoSlotResource::class;

    /**
     * Inyecta el horario_centro_id del centro del supervisor antes de crear el registro.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['horario_centro_id'])) {
            $uoId = auth()->user()?->uosActivas()->first()?->id;
            if ($uoId) {
                $centro = Centro::where('unidad_organizativa_id', $uoId)->first();
                if ($centro) {
                    $horario = HorarioCentro::where('centro_id', $centro->id)
                        ->where('activo', true)
                        ->orderByDesc('vigente_desde')
                        ->first();
                    if ($horario) {
                        $data['horario_centro_id'] = $horario->id;
                    }
                }
            }
        }

        return $data;
    }
}
