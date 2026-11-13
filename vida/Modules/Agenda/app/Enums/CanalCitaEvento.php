<?php

namespace Modules\Agenda\Enums;

/**
 * Canal por el que se hace una acción registrada en el historial de la cita.
 */
enum CanalCitaEvento: string
{
    case Presencial = 'presencial';
    case Telefonico = 'telefonico';
    case Interno = 'interno';
    case Api = 'api';

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
            self::Interno => 'Interno',
            self::Api => 'API externa',
        };
    }
}
