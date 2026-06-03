<?php

namespace Modules\Mensajes\Enums;

/**
 * Tipo de destinatario de una alerta del sistema.
 *
 * - usuario: alerta dirigida a un usuario concreto.
 * - rol_uo: alerta dirigida a todos los usuarios con un rol determinado
 *   en una Unidad Organizativa concreta.
 */
enum DestinatarioType: string
{
    case Usuario = 'usuario';
    case RolUo = 'rol_uo';
}
