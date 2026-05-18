<?php

namespace Modules\Intervencion\Enums;

enum VisibilidadApunte: string
{
    case Privada        = 'privada';
    case Profesionales  = 'profesionales';
    case Ciudadano      = 'ciudadano';

    public function label(): string
    {
        return match ($this) {
            self::Privada       => 'Privada (solo el autor)',
            self::Profesionales => 'Profesionales',
            self::Ciudadano     => 'Visible al ciudadano',
        };
    }
}
