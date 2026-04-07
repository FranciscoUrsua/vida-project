<?php

namespace Modules\Agenda\Enums;

enum OrigenCita: string
{
    case Interno    = 'interno';
    case ApiExterna = 'api_externa';

    public function label(): string
    {
        return match ($this) {
            self::Interno    => 'Interno',
            self::ApiExterna => 'API externa',
        };
    }
}
