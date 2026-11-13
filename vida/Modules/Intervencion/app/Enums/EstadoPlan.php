<?php

namespace Modules\Intervencion\Enums;

use App\Support\Ui\Tono;

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

    /**
     * Color del badge de estado del plan.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Borrador => Tono::Aviso,
            self::Activo => Tono::Exito,
            self::EnRevision => Tono::Primario,
            self::Cerrado => Tono::Neutro,
        };
    }
}
