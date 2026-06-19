<?php

namespace Modules\Documentos\Enums;

/**
 * Origenes posibles de un documento asociado al expediente.
 */
enum OrigenDocumento: string
{
    case Externo = 'externo';
    case Generado = 'generado';

    /**
     * Etiqueta legible para mostrar el origen del documento.
     */
    public function label(): string
    {
        return match ($this) {
            self::Externo => 'Externo',
            self::Generado => 'Generado',
        };
    }
}
