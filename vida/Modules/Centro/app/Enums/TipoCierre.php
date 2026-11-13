<?php

namespace Modules\Centro\Enums;

/**
 * Clase de cierre de calendario: el centro no abre ese día, o abre en un horario
 * reducido (docs/modulo-tipo-centro.md §5.2). Decide si se generan slots y en qué franja.
 */
enum TipoCierre: string
{
    case Cierre = 'cierre';
    case HorarioReducido = 'horario_reducido';

    /**
     * Etiqueta legible.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cierre => 'Cerrado',
            self::HorarioReducido => 'Horario reducido',
        };
    }
}
