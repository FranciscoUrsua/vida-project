<?php

namespace Modules\Centro\Enums;

/**
 * Cómo se asignó un centro a una persona (RN-11: toda asignación guarda cómo se hizo).
 *
 * @see docs/modulo-asignacion.md §3
 */
enum ModoAsignacionCentro: string
{
    case Geografico = 'geografico';
    case Eleccion = 'eleccion';
    case Manual = 'manual';

    /**
     * Etiqueta legible, tal como se muestra en la ficha («por domicilio»…).
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Geografico => 'Por domicilio',
            self::Eleccion => 'Elegido por la persona',
            self::Manual => 'Asignado por supervisión',
        };
    }
}
