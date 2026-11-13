<?php

namespace Modules\Agenda\Enums;

/**
 * A petición de quién se hace un cambio en una cita (reprogramación, cancelación).
 */
enum PedidoPor: string
{
    case Ciudadano = 'ciudadano';
    case Centro = 'centro';
    case Profesional = 'profesional';
    case Sistema = 'sistema';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Ciudadano => 'La persona',
            self::Centro => 'El centro',
            self::Profesional => 'El profesional',
            self::Sistema => 'El sistema',
        };
    }
}
