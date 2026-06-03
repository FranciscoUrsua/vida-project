<?php

namespace Modules\Escalas\Enums;

/**
 * Estado de un pase de escala.
 * El código toma decisiones sobre este valor (inmutabilidad de scores en completado,
 * validación de respuestas completas antes de completar), por eso es enum PHP.
 */
enum EstadoPase: string
{
    case Borrador = 'borrador';
    case Completado = 'completado';
}
