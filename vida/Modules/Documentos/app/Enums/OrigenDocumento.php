<?php

namespace Modules\Documentos\Enums;

enum OrigenDocumento: string
{
    case Externo = 'externo';
    case Generado = 'generado';

    public function label(): string
    {
        return match ($this) {
            self::Externo => 'Externo',
            self::Generado => 'Generado',
        };
    }
}
