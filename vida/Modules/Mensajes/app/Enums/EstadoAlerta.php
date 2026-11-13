<?php

namespace Modules\Mensajes\Enums;

use App\Support\Ui\Tono;

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

    /**
     * Color del badge de estado de un destinatario de la alerta.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Pendiente => Tono::Aviso,
            self::Reconocida => Tono::Exito,
            self::Escalada => Tono::Peligro,
            self::Vencida => Tono::Neutro,
        };
    }
}
