<?php

namespace Modules\Intervencion\Enums;

/**
 * Estados de una valoracion profesional dentro de la historia social.
 */
enum EstadoValoracion: string
{
    case Borrador = 'borrador';
    case Completada = 'completada';
    case Revisada = 'revisada';

    /**
     * Etiqueta legible para mostrar el estado de la valoracion.
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Completada => 'Completada',
            self::Revisada => 'Revisada',
        };
    }
}
