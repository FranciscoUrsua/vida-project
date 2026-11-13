<?php

namespace App\Filament\Resources\PrestacionResource\Pages;

use App\Filament\Resources\PrestacionResource;
use Filament\Resources\Pages\EditRecord;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Services\TiposCentroPrestacionService;

/**
 * Página de edición de prestaciones.
 */
class EditPrestacion extends EditRecord
{
    protected static string $resource = PrestacionResource::class;

    /**
     * Guarda los tipos de centro que ofrecen la prestación, con versión del estado anterior.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        /** @var Prestacion $prestacion */
        $prestacion = $this->record;
        $tiposCentro = is_array($this->data['tiposCentroKeys'] ?? null) ? $this->data['tiposCentroKeys'] : [];

        app(TiposCentroPrestacionService::class)->sincronizar($prestacion, array_map('intval', $tiposCentro), auth()->user());
    }
}
