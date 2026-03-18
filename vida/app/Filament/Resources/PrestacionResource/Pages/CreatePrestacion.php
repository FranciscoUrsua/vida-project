<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Prestaciones\Models\PrestacionTipoCentro;

class CreatePrestacion extends CreateRecord
{
    protected static string $resource = PrestacionResource::class;

    protected function afterCreate(): void
    {
        $tiposCentro = $this->data['tiposCentroKeys'] ?? [];

        foreach ($tiposCentro as $tipo) {
            PrestacionTipoCentro::create([
                'prestacion_id' => $this->record->id,
                'tipo_centro'   => $tipo,
            ]);
        }
    }
}
