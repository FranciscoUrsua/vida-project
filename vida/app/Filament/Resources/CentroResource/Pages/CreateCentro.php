<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use Modules\Centro\Services\TipoCentro\CentroService;

/**
 * Página de creación de centros: el alta pasa por CentroService (tipo activo y denominación inicial).
 */
class CreateCentro extends CreateRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * Da de alta el centro con su tipo y su denominación inicial.
     *
     * @param array<string, mixed> $data Datos del formulario.
     *
     * @return Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tipo = TipoCentro::findOrFail($data['tipo_centro_id']);
        $antecesor = isset($data['centro_antecesor_id']) ? Centro::withTrashed()->find($data['centro_antecesor_id']) : null;

        return app(CentroService::class)->darDeAlta($data, $tipo, $antecesor, auth()->user());
    }
}
