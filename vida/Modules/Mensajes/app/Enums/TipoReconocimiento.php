<?php

namespace Modules\Mensajes\Enums;

/**
 * Naturaleza del reconocimiento de una alerta por parte de un usuario.
 *
 * - reconocida: el destinatario original marcó la alerta como leída.
 * - escalada: el supervisor heredó la alerta tras un vencimiento de plazo.
 * - descartada: el destinatario descartó un aviso (sin confirmación requerida).
 */
enum TipoReconocimiento: string
{
    case Reconocida = 'reconocida';
    case Escalada   = 'escalada';
    case Descartada = 'descartada';
}
