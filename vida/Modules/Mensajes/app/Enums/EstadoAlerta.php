<?php

namespace Modules\Mensajes\Enums;

/**
 * Estado del ciclo de vida de una alerta.
 *
 * pendiente  → reconocida (fin)
 * pendiente  → escalada (si vence el plazo de reconocimiento)
 * escalada   → reconocida (el supervisor la cierra; no tiene plazo)
 * pendiente  → vencida (si vence el plazo y no hay supervisor al que escalar)
 *
 * Se aplica a cada destinatario (`alerta_destinatarios`); el estado de la
 * alerta es el resumen de los de sus destinatarios.
 */
enum EstadoAlerta: string
{
    case Pendiente = 'pendiente';
    case Reconocida = 'reconocida';
    case Escalada = 'escalada';
    case Vencida = 'vencida';
}
