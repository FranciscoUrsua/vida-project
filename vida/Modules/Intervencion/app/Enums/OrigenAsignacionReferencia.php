<?php

namespace Modules\Intervencion\Enums;

/**
 * Cómo se hizo cada asignación de profesional de referencia (RN-11).
 *
 * Distinto a propósito de ModoAsignacionReferenciaCentro (la configuración del
 * centro): este tiene más valores. Solo cuentan como entrada en el reparto las
 * de sorteo y elección (docs/modulo-asignacion.md §4.2).
 */
enum OrigenAsignacionReferencia: string
{
    case Sorteo = 'sorteo';
    case Eleccion = 'eleccion';
    case UnidadConvivencia = 'unidad_convivencia';
    case Reparto = 'reparto';
    case Manual = 'manual';
    case QuienAbre = 'quien_abre';

    /**
     * Etiqueta legible, tal como se muestra en la ficha («por sorteo», «elegido»…).
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Sorteo => 'Por sorteo',
            self::Eleccion => 'Elegido por la persona',
            self::UnidadConvivencia => 'Por su unidad de convivencia',
            self::Reparto => 'Por reparto de casos',
            self::Manual => 'Asignado por supervisión',
            self::QuienAbre => 'Abrió la historia',
        };
    }

    /**
     * Si la asignación cuenta como entrada nueva en el reparto proporcional.
     *
     * @return bool
     */
    public function cuentaEnReparto(): bool
    {
        return match ($this) {
            self::Sorteo, self::Eleccion => true,
            default => false,
        };
    }
}
