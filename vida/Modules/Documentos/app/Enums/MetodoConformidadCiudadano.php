<?php

namespace Modules\Documentos\Enums;

enum MetodoConformidadCiudadano: string
{
    case ManuscritaEscaneada = 'manuscrita_escaneada';

    public function label(): string
    {
        return match ($this) {
            self::ManuscritaEscaneada => 'Firma manuscrita escaneada',
        };
    }
}
