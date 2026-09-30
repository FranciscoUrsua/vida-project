<?php

namespace Modules\Centro\Enums;

/**
 * Estado de una entrada de la bandeja de asignaciones. Las entradas no se
 * borran: se resuelven (con asignación) o se descartan, con rastro.
 */
enum EstadoAsignacionPendiente: string
{
    case Pendiente = 'pendiente';
    case Resuelta = 'resuelta';
    case Descartada = 'descartada';
}
