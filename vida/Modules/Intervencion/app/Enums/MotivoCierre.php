<?php

namespace Modules\Intervencion\Enums;

enum MotivoCierre: string
{
    case ObjetivosCumplidos = 'objetivos_cumplidos';
    case Abandono = 'abandono';
    case Derivacion = 'derivacion';
    case Fallecimiento = 'fallecimiento';
    case Otros = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::ObjetivosCumplidos => 'Objetivos cumplidos',
            self::Abandono => 'Abandono',
            self::Derivacion => 'Derivación',
            self::Fallecimiento => 'Fallecimiento',
            self::Otros => 'Otros',
        };
    }
}
