<?php

namespace Modules\Intervencion\Enums;

/**
 * Niveles de visibilidad de un apunte de intervención.
 */
enum VisibilidadApunte: string
{
    case Privada = 'privada';
    case Profesionales = 'profesionales';
    case Ciudadano = 'ciudadano';

    /**
     * Devuelve la etiqueta legible de la visibilidad.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Privada => 'Privada (solo el autor)',
            self::Profesionales => 'Profesionales',
            self::Ciudadano => 'Visible al ciudadano',
        };
    }
}
