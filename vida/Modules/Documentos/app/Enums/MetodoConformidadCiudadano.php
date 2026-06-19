<?php

namespace Modules\Documentos\Enums;

/**
 * Metodos aceptados para registrar la conformidad del ciudadano.
 */
enum MetodoConformidadCiudadano: string
{
    case ManuscritaEscaneada = 'manuscrita_escaneada';

    /**
     * Etiqueta legible para mostrar el metodo de conformidad.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManuscritaEscaneada => 'Firma manuscrita escaneada',
        };
    }
}
