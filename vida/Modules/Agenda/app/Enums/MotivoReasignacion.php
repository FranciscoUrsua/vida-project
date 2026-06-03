<?php

namespace Modules\Agenda\Enums;

enum MotivoReasignacion: string
{
    case NoShowProfesional = 'no_show_profesional';
    case BajaSobrevenida = 'baja_sobrevenida';
    case Redistribucion = 'redistribucion';
    case Otros = 'otros';

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
