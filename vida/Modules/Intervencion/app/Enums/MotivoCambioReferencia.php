<?php

namespace Modules\Intervencion\Enums;

/**
 * Motivos por los que la supervisión cambia el profesional de referencia (fase 3, E2).
 *
 * Es enum porque el código decide con él: «otro» exige texto libre. El motivo se guarda
 * como texto en `asignaciones_profesional.motivo` (etiqueta, y el texto si es «otro»).
 */
enum MotivoCambioReferencia: string
{
    case Conflicto = 'conflicto';
    case Parentesco = 'parentesco';
    case Incompatibilidad = 'incompatibilidad';
    case Correccion = 'correccion';
    case Otro = 'otro';

    /**
     * Etiqueta para la interfaz y para el motivo guardado.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Conflicto => 'Conflicto',
            self::Parentesco => 'Parentesco',
            self::Incompatibilidad => 'Incompatibilidad',
            self::Correccion => 'Corrección',
            self::Otro => 'Otro',
        };
    }

    /**
     * Si el motivo exige explicarlo con texto.
     *
     * @return bool
     */
    public function requiereTexto(): bool
    {
        return $this === self::Otro;
    }
}
