<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\PrestacionTipoCentro;

/**
 * Página de edición de prestaciones.
 */
class EditPrestacion extends EditRecord
{
    protected static string $resource = PrestacionResource::class;

    protected function afterSave(): void
    {
        /** @var Prestacion $prestacion */
        $prestacion = $this->record;
        $tiposCentro = is_array($this->data['tiposCentroKeys'] ?? null)
            ? $this->data['tiposCentroKeys']
            : [];

        $prestacion->tiposCentro()->delete();

        foreach ($tiposCentro as $tipo) {
            PrestacionTipoCentro::create([
                'prestacion_id' => $prestacion->id,
                'tipo_centro' => $tipo,
            ]);
        }
    }
}
