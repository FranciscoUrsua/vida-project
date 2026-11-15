<?php

namespace Modules\Prestaciones\Enums;

/**
 * De dónde viene un cambio en una solicitud o en su expediente (RN-07 y RN-11).
 *
 * Se guarda en cada evento del historial; la resolución y el apunte automático usan
 * la fuente para distinguir lo que llegó del gestor de lo registrado a mano.
 */
enum OrigenActualizacion: string
{
    case Usuario = 'usuario';
    case AvisoGestor = 'aviso_gestor';
    case ConsultaAutomatica = 'consulta_automatica';
    case ConsultaManual = 'consulta_manual';
    case RegistroManual = 'registro_manual';
    case Sistema = 'sistema';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Usuario => 'Usuario',
            self::AvisoGestor => 'Aviso del gestor',
            self::ConsultaAutomatica => 'Consulta automática',
            self::ConsultaManual => 'Consulta manual',
            self::RegistroManual => 'Registro manual',
            self::Sistema => 'Sistema',
        };
    }
}
