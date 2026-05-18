<?php

namespace Modules\Intervencion\Enums;

enum EstadoValoracion: string
{
    case Borrador   = 'borrador';
    case Completada = 'completada';
    case Revisada   = 'revisada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador   => 'Borrador',
            self::Completada => 'Completada',
            self::Revisada   => 'Revisada',
        };
    }
}
