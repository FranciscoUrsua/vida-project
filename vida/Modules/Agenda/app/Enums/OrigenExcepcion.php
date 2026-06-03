<?php

namespace Modules\Agenda\Enums;

enum OrigenExcepcion: string
{
    case Manual = 'manual';
    case ApiRrhh = 'api_rrhh';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::ApiRrhh => 'API RRHH',
        };
    }
}
