<?php

namespace Modules\Agenda\Enums;

/**
 * Quién hace una acción sobre una solicitud o una cita.
 */
enum ActorTipoCitaEvento: string
{
    case Usuario = 'usuario';
    case ApiExterna = 'api_externa';
    case Sistema = 'sistema';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Usuario => 'Usuario',
            self::ApiExterna => 'Cita previa (canal externo)',
            self::Sistema => 'Sistema',
        };
    }
}
