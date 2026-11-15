<?php

namespace Modules\Prestaciones\Enums;

/**
 * Sentido global de una resolución (§2.5 del diseño). Decide el texto del aviso al profesional.
 */
enum SentidoResolucion: string
{
    case Favorable = 'favorable';
    case Parcial = 'parcial';
    case Desfavorable = 'desfavorable';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Favorable => 'Favorable',
            self::Parcial => 'Parcialmente favorable',
            self::Desfavorable => 'Desfavorable',
        };
    }
}
