<?php

namespace Modules\Documentos\Enums;

enum EstadoInforme: string
{
    case Borrador = 'borrador';
    case Firmado = 'firmado';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Firmado => 'Firmado',
            self::Anulado => 'Anulado',
        };
    }
}
