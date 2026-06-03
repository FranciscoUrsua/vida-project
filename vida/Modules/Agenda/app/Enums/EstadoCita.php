<?php

namespace Modules\Agenda\Enums;

enum EstadoCita: string
{
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';
    case Completada = 'completada';
    case NoShowCiudadano = 'no_show_ciudadano';
    case NoShowProfesional = 'no_show_profesional';
    case Reasignada = 'reasignada';

    public function label(): string
    {
        return match ($this) {
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
            self::Completada => 'Completada',
            self::NoShowCiudadano => 'No presentado (ciudadano)',
            self::NoShowProfesional => 'No presentado (profesional)',
            self::Reasignada => 'Reasignada',
        };
    }
}
