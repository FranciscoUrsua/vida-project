<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\PrestacionTipoCentro;

/**
 * Página de creación de prestaciones.
 */
class CreatePrestacion extends CreateRecord
{
    protected static string $resource = PrestacionResource::class;

    protected function afterCreate(): void
    {
        /** @var Prestacion $prestacion */
        $prestacion = $this->record;
        $tiposCentro = is_array($this->data['tiposCentroKeys'] ?? null)
            ? $this->data['tiposCentroKeys']
            : [];

        foreach ($tiposCentro as $tipo) {
            PrestacionTipoCentro::create([
                'prestacion_id' => $prestacion->id,
                'tipo_centro' => $tipo,
            ]);
        }
    }
}
