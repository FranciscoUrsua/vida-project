<?php

namespace App\Filament\Resources\TipoEscalaResource\Pages;

use App\Filament\Resources\TipoEscalaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoEscala extends CreateRecord
{
    protected static string $resource = TipoEscalaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['schema'] = ['secciones' => $data['secciones_escala'] ?? []];
        $data['rangos_interpretacion'] = [
            'rangos'              => $data['rangos'] ?? [],
            'nota_interpretacion' => $data['nota_interpretacion'] ?? null,
        ];

        unset($data['secciones_escala'], $data['rangos'], $data['nota_interpretacion']);

        return $data;
    }
}
