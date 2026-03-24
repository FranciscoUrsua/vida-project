<?php

namespace Modules\Mensajes\Enums;

/**
 * Estado del ciclo de vida de una alerta.
 *
 * pendiente  → reconocida (fin)
 * pendiente  → escalada (si vence el plazo de reconocimiento)
 * escalada   → reconocida (por el supervisor)
 * escalada   → vencida (si el supervisor tampoco reconoce en plazo)
 */
enum EstadoAlerta: string
{
    case Pendiente  = 'pendiente';
    case Reconocida = 'reconocida';
    case Escalada   = 'escalada';
    case Vencida    = 'vencida';
}
