<?php

namespace Modules\Intervencion\Enums;

enum TipoApunte: string
{
    case Entrevista  = 'entrevista';
    case Documento   = 'documento';
    case Derivacion  = 'derivacion';
    case Seguimiento = 'seguimiento';
    case Anotacion   = 'anotacion';

    public function label(): string
    {
        return match ($this) {
            self::Entrevista  => 'Entrevista',
            self::Documento   => 'Documento',
            self::Derivacion  => 'Derivación',
            self::Seguimiento => 'Seguimiento',
            self::Anotacion   => 'Anotación',
        };
    }
}
