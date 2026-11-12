<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Services\TiposCentroPrestacionService;

/**
 * Página de creación de prestaciones.
 */
class CreatePrestacion extends CreateRecord
{
    protected static string $resource = PrestacionResource::class;

    /**
     * Guarda los tipos de centro que ofrecen la prestación recién creada.
     *
     * @return void
     */
    protected function afterCreate(): void
    {
        /** @var Prestacion $prestacion */
        $prestacion = $this->record;
        $tiposCentro = is_array($this->data['tiposCentroKeys'] ?? null) ? $this->data['tiposCentroKeys'] : [];

        app(TiposCentroPrestacionService::class)->sincronizar($prestacion, array_map('intval', $tiposCentro), auth()->user(), versionar: false);
    }
}
