<?php

namespace App\Filament\Resources\HorarioCentroResource\Pages;

use App\Filament\Resources\HorarioCentroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;

/**
 * Página de creación de horarios de centro.
 */
class CreateHorarioCentro extends CreateRecord
{
    protected static string $resource = HorarioCentroResource::class;

    /**
     * Crea el horario si el usuario gestiona el calendario del centro elegido
     * (el director, solo el suyo: §9), aunque la petición traiga otro centro.
     *
     * @param array<string, mixed> $data
     *
     * @return Model
     */
    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('gestionarCalendario', Centro::findOrFail($data['centro_id'] ?? 0));

        return parent::handleRecordCreation($data);
    }
}
