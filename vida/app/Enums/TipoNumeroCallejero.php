<?php

namespace App\Enums;

/**
 * Tipo de un número de policía del callejero oficial de Madrid («Tipologia del numero»).
 *
 * Es enum y no catálogo porque el código decide con él: el autocompletado del domicilio
 * ofrece primero portales y parcelas, que son donde vive la gente (en la Cañada Real hay
 * viviendas registradas como parcela). Se importan todos los tipos (decisión del
 * 24 nov 2026).
 */
enum TipoNumeroCallejero: string
{
    case Portal = 'portal';
    case FrenteFachada = 'frente_fachada';
    case Parcela = 'parcela';
    case Garaje = 'garaje';
    case Jardin = 'jardin';
    case Otro = 'otro';

    /**
     * Tipo correspondiente al literal del fichero municipal; `Otro` si viene vacío o es nuevo.
     *
     * @param string $tipologia Valor de «Tipologia del numero», p. ej. «Frente fachada».
     *
     * @return self
     */
    public static function desdeTipologia(string $tipologia): self
    {
        return match (mb_strtolower(trim($tipologia))) {
            'portal' => self::Portal,
            'frente fachada' => self::FrenteFachada,
            'parcela' => self::Parcela,
            'garaje' => self::Garaje,
            'jardín o parque público', 'jardin o parque publico' => self::Jardin,
            default => self::Otro,
        };
    }

    /**
     * Etiqueta legible del tipo de número.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Portal => 'Portal',
            self::FrenteFachada => 'Frente de fachada',
            self::Parcela => 'Parcela',
            self::Garaje => 'Garaje',
            self::Jardin => 'Jardín o parque',
            self::Otro => 'Otro',
        };
    }
}
