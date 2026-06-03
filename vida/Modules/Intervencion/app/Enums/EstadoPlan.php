<?php

namespace Modules\Intervencion\Enums;

enum EstadoPlan: string
{
    case Borrador = 'borrador';
    case Activo = 'activo';
    case EnRevision = 'en_revision';
    case Cerrado = 'cerrado';

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
