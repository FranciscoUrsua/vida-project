<?php

namespace Modules\Agenda\Enums;

enum ModoAgenda: string
{
    case Basico = 'basico';
    case Estandar = 'estandar';
    case Avanzado = 'avanzado';

    public function label(): string
    {
        return match ($this) {
            self::Basico => 'Básico',
            self::Estandar => 'Estándar',
            self::Avanzado => 'Avanzado',
        };
    }
}
