<?php

namespace Modules\Agenda\Enums;

/**
 * Por dónde llega la petición de una cita del canal interno. Se guarda para
 * medir demanda y demora por canal (docs/modulo-citas.md §2.2).
 */
enum CanalSolicitudCita: string
{
    case Presencial = 'presencial';
    case Telefonico = 'telefonico';
    case Interno = 'interno';
    case Seguimiento = 'seguimiento';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Telefonico => 'Teléfono',
            self::Interno => 'Solicitud interna',
            self::Seguimiento => 'Seguimiento de plan',
        };
    }
}
