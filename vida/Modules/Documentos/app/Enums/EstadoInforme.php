<?php

namespace Modules\Documentos\Enums;

/**
 * Estados del ciclo de vida de un informe documental.
 */
enum EstadoInforme: string
{
    case Borrador = 'borrador';
    case Firmado = 'firmado';
    case Anulado = 'anulado';

    /**
     * Etiqueta legible para mostrar el estado del informe.
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Firmado => 'Firmado',
            self::Anulado => 'Anulado',
        };
    }
}
