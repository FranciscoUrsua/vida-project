<?php

namespace Modules\Intervencion\Enums;

/**
 * Motivos normalizados para cerrar un plan o proceso de intervencion.
 */
enum MotivoCierre: string
{
    case ObjetivosCumplidos = 'objetivos_cumplidos';
    case Abandono = 'abandono';
    case Derivacion = 'derivacion';
    case Fallecimiento = 'fallecimiento';
    case Otros = 'otros';

    /**
     * Etiqueta legible para mostrar el motivo de cierre.
     *
     * @return string
     */
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
