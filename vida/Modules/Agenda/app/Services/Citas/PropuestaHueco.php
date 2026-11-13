<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\User;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Models\Slot;

/**
 * Hueco propuesto para una solicitud: el slot, su profesional y el modo de
 * asignación que tendría la cita. Solo lectura: el sistema propone y la
 * persona que cita elige (principio 3.10).
 */
final class PropuestaHueco
{
    /**
     * @param Slot $slot
     * @param User $profesional
     * @param ModoAsignacionCita $modo
     */
    public function __construct(
        public readonly Slot $slot,
        public readonly User $profesional,
        public readonly ModoAsignacionCita $modo,
    ) {}
}
