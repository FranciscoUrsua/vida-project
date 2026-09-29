<?php

namespace Modules\Agenda\Enums;

use App\Support\Ui\Tono;

/**
 * Tipos de excepcion que afectan a la disponibilidad profesional.
 */
enum TipoExcepcion: string
{
    case BajaMedica = 'baja_medica';
    case Vacaciones = 'vacaciones';
    case DiaLibre = 'dia_libre';
    case Formacion = 'formacion';
    case ReduccionJornada = 'reduccion_jornada';
    case Guardia = 'guardia';
    case Otros = 'otros';

    /**
     * Etiqueta legible para mostrar el tipo de excepcion.
     */
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

    /**
     * Color del badge del tipo de excepción de horario.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::BajaMedica => Tono::Peligro,
            self::Vacaciones => Tono::Exito,
            self::ReduccionJornada => Tono::Aviso,
            self::DiaLibre => Tono::Info,
            self::Formacion, self::Guardia, self::Otros => Tono::Neutro,
        };
    }
}
