<?php

namespace Modules\Intervencion\Enums;

/**
 * Estados del ciclo de vida de un plan de intervencion.
 */
enum EstadoPlan: string
{
    case Borrador = 'borrador';
    case Activo = 'activo';
    case EnRevision = 'en_revision';
    case Cerrado = 'cerrado';

    /**
     * Etiqueta legible para mostrar el estado del plan.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Activo => 'Activo',
            self::EnRevision => 'En revisión',
            self::Cerrado => 'Cerrado',
        };
    }
}
