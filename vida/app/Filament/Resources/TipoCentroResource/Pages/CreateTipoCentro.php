<?php

namespace App\Filament\Resources\TipoCentroResource\Pages;

use App\Filament\Resources\TipoCentroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Centro\Services\TipoCentro\TipoCentroService;

/**
 * Página de creación de tipos de centro: el alta pasa por TipoCentroService
 * (denominación inicial y horario por defecto); los catálogos y el público los
 * guarda el formulario después, sobre el tipo ya creado.
 */
class CreateTipoCentro extends CreateRecord
{
    protected static string $resource = TipoCentroResource::class;

    /**
     * Da de alta el tipo con su denominación y su horario inicial.
     *
     * @param array<string, mixed> $data Datos del formulario.
     *
     * @return Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        $horario = $data['horario'] ?? [];
        $horario['dias_laborables'] = array_map('intval', $horario['dias_laborables'] ?? []);

        return app(TipoCentroService::class)->crear(
            array_merge(Arr::except($data, ['horario']), ['horario' => $horario]),
            auth()->user(),
        );
    }
}
