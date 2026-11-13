<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\TipoCentro\CentroService;

/**
 * Página de edición de un centro. Las prestaciones no se editan aquí: son las
 * del catálogo de su tipo (docs/modulo-tipo-centro.md §6.1).
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * Guarda por CentroService solo los campos que cambian: así se aplican en el
     * servidor los límites del director y de las propiedades «solo tipo» (§9).
     *
     * @param Model $record
     * @param array<string, mixed> $data
     *
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Centro $record */
        $cambios = array_filter(
            $data,
            // Comparación laxa a propósito: el formulario manda cadenas y el modelo tiene casts
            fn ($valor, string $campo) => $record->isFillable($campo) && $record->getAttribute($campo) != $valor,
            ARRAY_FILTER_USE_BOTH,
        );

        return app(CentroService::class)->actualizar($record, $cambios, auth()->user());
    }
}
