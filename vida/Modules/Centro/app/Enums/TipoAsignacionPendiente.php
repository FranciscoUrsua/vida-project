<?php

namespace Modules\Centro\Enums;

/**
 * Qué falta por decidir en una entrada de la bandeja de asignaciones.
 *
 * @see docs/modulo-asignacion.md §7
 */
enum TipoAsignacionPendiente: string
{
    case SinCentro = 'sin_centro';
    case SinReferencia = 'sin_referencia';
    case CambioDomicilio = 'cambio_domicilio';

    /**
     * Etiqueta legible del tipo de pendiente.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::SinCentro => 'Sin centro',
            self::SinReferencia => 'Sin profesional de referencia',
            self::CambioDomicilio => 'Cambio de domicilio',
        };
    }
}
