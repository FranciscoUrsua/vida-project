<?php

namespace Modules\Agenda\Observers;

use LogicException;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;

/**
 * Observer del modelo Cita.
 *
 * Mantiene la coherencia entre el estado de la Cita y el del Slot asociado
 * a lo largo del ciclo de vida de la reserva.
 */
class CitaObserver
{
    /**
     * Impide reservar un slot de urgencia desde el canal externo.
     *
     * @param Cita $cita Cita que se está creando.
     *
     * @return void
     *
     * @throws LogicException
     */
    public function creating(Cita $cita): void
    {
        $slot = Slot::find($cita->slot_id);

        if ($slot
            && $slot->estado === EstadoSlot::BloqueadoUrgencia
            && $cita->origen === OrigenCita::ApiExterna
        ) {
            throw new LogicException(
                'Los slots de urgencia no son reservables desde el canal externo.'
            );
        }
    }

    /**
     * Marca el slot como reservado al crear la cita.
     *
     * @param Cita $cita Cita recién creada.
     *
     * @return void
     */
    public function created(Cita $cita): void
    {
        Slot::where('id', $cita->slot_id)
            ->update(['estado' => EstadoSlot::Reservado->value]);
    }
}
