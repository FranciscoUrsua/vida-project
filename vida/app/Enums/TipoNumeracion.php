<?php

namespace App\Enums;

/**
 * Tipo de numeración de la vía en una dirección postal.
 *
 * El código toma decisiones basadas en este valor — por eso es enum
 * y no un valor de catálogo. Ver principio 3.10 de principios-vida360.md.
 */
enum TipoNumeracion: string
{
    case Numero = 'numero';
    case SinNumero = 'sin_numero';
    case Km = 'km';

    public function label(): string
    {
        return match ($this) {
            self::Numero => 'Número',
            self::SinNumero => 'Sin número',
            self::Km => 'Kilómetro',
        };
    }
}
