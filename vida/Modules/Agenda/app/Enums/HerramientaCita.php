<?php

namespace Modules\Agenda\Enums;

/**
 * Herramienta de Intervención que se abre al atender una cita de un tipo dado.
 * Es enum porque la agenda decide con ella qué pantalla abrir.
 */
enum HerramientaCita: string
{
    case EntrevistaInicial = 'entrevista_inicial';
    case EntrevistaSeguimiento = 'entrevista_seguimiento';
    case Valoracion = 'valoracion';
    case Plan = 'plan';
    case Atencion = 'atencion';
    case Ninguna = 'ninguna';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::EntrevistaInicial => 'Entrevista inicial',
            self::EntrevistaSeguimiento => 'Entrevista de seguimiento',
            self::Valoracion => 'Valoración',
            self::Plan => 'Plan de intervención',
            self::Atencion => 'Registro de atención',
            self::Ninguna => 'Ninguna',
        };
    }
}
