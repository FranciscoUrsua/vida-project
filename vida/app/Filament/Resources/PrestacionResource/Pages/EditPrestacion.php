<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Prestaciones\Models\PrestacionTipoCentro;

/**
 * Página de edición de prestaciones.
 */
class EditPrestacion extends EditRecord
{
    protected static string $resource = PrestacionResource::class;

    protected function afterSave(): void
    {
        $tiposCentro = $this->data['tiposCentroKeys'] ?? [];

        // Sincronizar tipos de centro
        $this->record->tiposCentro()->delete();
        foreach ($tiposCentro as $tipo) {
            PrestacionTipoCentro::create([
                'prestacion_id' => $this->record->id,
                'tipo_centro' => $tipo,
            ]);
        }
    }
}
