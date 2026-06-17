<?php

namespace Modules\Agenda\Enums;

/**
 * Origenes habilitados para reservar un tipo de slot.
 */
enum OrigenPermitidoSlot: string
{
    case Interno = 'interno';
    case ApiExterna = 'api_externa';
    case Ambos = 'ambos';

    /**
     * Etiqueta legible para mostrar los origenes permitidos del slot.
     */
    public function label(): string
    {
        return match ($this) {
            self::Interno => 'Solo interno',
            self::ApiExterna => 'Solo API externa',
            self::Ambos => 'Ambos',
        };
    }
}
