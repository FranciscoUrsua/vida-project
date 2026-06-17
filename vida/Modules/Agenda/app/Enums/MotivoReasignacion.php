<?php

namespace Modules\Agenda\Enums;

/**
 * Motivos normalizados para registrar la reasignacion de una cita.
 */
enum MotivoReasignacion: string
{
    case NoShowProfesional = 'no_show_profesional';
    case BajaSobrevenida = 'baja_sobrevenida';
    case Redistribucion = 'redistribucion';
    case Otros = 'otros';

    /**
     * Etiqueta legible para mostrar el motivo de reasignacion.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NoShowProfesional => 'No presentación del profesional',
            self::BajaSobrevenida => 'Baja sobrevenida',
            self::Redistribucion => 'Redistribución de carga',
            self::Otros => 'Otros',
        };
    }
}
