<?php

namespace Modules\Agenda\Enums;

/**
 * Modos de configuracion de la agenda por nivel de detalle.
 */
enum ModoAgenda: string
{
    case Basico = 'basico';
    case Estandar = 'estandar';
    case Avanzado = 'avanzado';

    /**
     * Etiqueta legible para mostrar el modo de agenda.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Basico => 'Básico',
            self::Estandar => 'Estándar',
            self::Avanzado => 'Avanzado',
        };
    }
}
