<?php

namespace Modules\Mensajes\Enums;

/**
 * Tipo de destinatario de una alerta del sistema.
 *
 * - usuario: alerta dirigida a un usuario concreto.
 * - rol_uo: alerta dirigida a todos los usuarios con un rol determinado
 *   en una Unidad Organizativa concreta.
 * - uo: aviso a todo el equipo de una Unidad Organizativa (avisos manuales
 *   del supervisor; el remitente no se incluye).
 *
 * En `rol_uo` y `uo` cada destinatario debe reconocerla por su cuenta.
 */
enum DestinatarioType: string
{
    case Usuario = 'usuario';
    case RolUo = 'rol_uo';
    case Uo = 'uo';
}
