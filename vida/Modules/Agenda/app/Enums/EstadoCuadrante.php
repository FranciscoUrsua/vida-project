<?php

namespace Modules\Agenda\Enums;

enum EstadoCuadrante: string
{
    case Borrador = 'borrador';
    case Revision = 'revision';
    case Publicado = 'publicado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Revision => 'En revisión',
            self::Publicado => 'Publicado',
        };
    }
}
