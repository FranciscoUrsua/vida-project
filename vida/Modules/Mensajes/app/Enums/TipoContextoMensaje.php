<?php

namespace Modules\Mensajes\Enums;

/**
 * Tipos de elemento que pueden vincularse a una conversación desde el panel
 * de redacción, o desde la agenda al pedir un cambio (cita y slot). Es enum
 * porque el código decide con él qué modelo cargar, qué permiso comprobar, a
 * quién sugerir como destinatario y a qué ruta enlazar.
 */
enum TipoContextoMensaje: string
{
    case Historia = 'historia';
    case Ficha = 'ficha';
    case Plan = 'plan';
    case Cita = 'cita';
    case Slot = 'slot';

    /**
     * Nombre del tipo de elemento para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Historia => 'Historia Social',
            self::Ficha => 'Ficha de valoración',
            self::Plan => 'Plan de intervención',
            self::Cita => 'Cita',
            self::Slot => 'Hueco de agenda',
        };
    }
}
