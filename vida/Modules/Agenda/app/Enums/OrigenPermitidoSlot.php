<?php

namespace Modules\Agenda\Enums;

enum OrigenPermitidoSlot: string
{
    case Interno    = 'interno';
    case ApiExterna = 'api_externa';
    case Ambos      = 'ambos';

    public function label(): string
    {
        return match ($this) {
            self::Interno    => 'Solo interno',
            self::ApiExterna => 'Solo API externa',
            self::Ambos      => 'Ambos',
        };
    }
}
