<?php

namespace App\Filament\Resources\HorarioCentroResource\Pages;

use App\Filament\Resources\HorarioCentroResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\Centro\Models\Centro;

/**
 * Página de edición de horarios de centro.
 */
class EditHorarioCentro extends EditRecord
{
    protected static string $resource = HorarioCentroResource::class;

    /**
     * Acciones de la cabecera.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * Guarda si el usuario gestiona el calendario del centro del horario y del
     * centro al que lo mueve (§9), aunque la petición traiga otro centro.
     *
     * @param Model $record
     * @param array<string, mixed> $data
     *
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Gate::authorize('gestionarCalendario', $record->centro);
        if (isset($data['centro_id']) && (int) $data['centro_id'] !== $record->centro_id) {
            Gate::authorize('gestionarCalendario', Centro::findOrFail($data['centro_id']));
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
