<?php

namespace Modules\Mensajes\Enums;

/**
 * Visibilidad de un registro de mensaje en la Historia Social.
 *
 * - privada: solo visible para el profesional que lo registró.
 * - profesionales: visible para todos los profesionales con acceso
 *   a la Historia Social del ciudadano.
 *
 * Nota: la visibilidad 'ciudadano' no aplica aquí ya que se trata
 * de comunicación interna entre profesionales.
 */
enum VisibilidadMensaje: string
{
    case Privada = 'privada';
    case Profesionales = 'profesionales';
}
