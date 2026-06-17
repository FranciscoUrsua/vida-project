<?php

namespace Modules\Agenda\Enums;

/**
 * Origenes desde los que puede crearse una cita.
 */
enum OrigenCita: string
{
    case Interno = 'interno';
    case ApiExterna = 'api_externa';

    /**
     * Etiqueta legible para mostrar el origen de la cita.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Interno => 'Interno',
            self::ApiExterna => 'API externa',
        };
    }
}
