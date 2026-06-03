<?php

namespace Modules\Agenda\Enums;

enum TipoExcepcion: string
{
    case BajaMedica = 'baja_medica';
    case Vacaciones = 'vacaciones';
    case DiaLibre = 'dia_libre';
    case Formacion = 'formacion';
    case ReduccionJornada = 'reduccion_jornada';
    case Guardia = 'guardia';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::BajaMedica => 'Baja médica',
            self::Vacaciones => 'Vacaciones',
            self::DiaLibre => 'Día libre',
            self::Formacion => 'Formación',
            self::ReduccionJornada => 'Reducción de jornada',
            self::Guardia => 'Guardia',
            self::Otros => 'Otros',
        };
    }
}
