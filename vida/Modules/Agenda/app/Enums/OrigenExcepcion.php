<?php

namespace Modules\Agenda\Enums;

/**
 * Origenes desde los que puede registrarse una excepcion profesional.
 */
enum OrigenExcepcion: string
{
    case Manual = 'manual';
    case ApiRrhh = 'api_rrhh';

    /**
     * Etiqueta legible para mostrar el origen de la excepcion.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::ApiRrhh => 'API RRHH',
        };
    }
}
