<?php

namespace Modules\Documentos\Enums;

use App\Support\Ui\Tono;

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
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Firmado => 'Firmado',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * Tono con el que se pinta el estado en la interfaz operativa.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Borrador => Tono::Aviso,
            self::Firmado => Tono::Exito,
            self::Anulado => Tono::Neutro,
        };
    }
}
