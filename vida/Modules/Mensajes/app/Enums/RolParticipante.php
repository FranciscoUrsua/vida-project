<?php

namespace Modules\Mensajes\Enums;

/**
 * Rol de un usuario dentro de un hilo de mensajes.
 *
 * - remitente_inicial: usuario que creó el hilo y envió el primer mensaje.
 * - participante: el otro interlocutor en la conversación 1 a 1.
 */
enum RolParticipante: string
{
    case RemitenteInicial = 'remitente_inicial';
    case Participante = 'participante';
}
