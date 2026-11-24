<?php

namespace App\Services\Geocodificacion\Callejero;

use Illuminate\Support\Str;

/**
 * Forma canónica de un texto para buscarlo en el callejero: minúsculas, sin tildes y con
 * los espacios normalizados.
 *
 * La usan la importación (columna `nombre_via_busqueda`) y la búsqueda, y tiene que ser
 * la misma en los dos lados para que «Albúfera», «ALBUFERA» y «albufera» casen.
 */
final class TextoBusqueda
{
    /**
     * Normaliza el texto para compararlo con `callejero_portales.nombre_via_busqueda`.
     *
     * @param string $texto Texto en cualquier forma («ABAD JUAN CATALÁN», «Abuñol»…).
     *
     * @return string
     */
    public static function normalizar(string $texto): string
    {
        // Str::ascii translitera (á → a, ñ → n, ü → u) sin perder letras
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($texto))));
    }
}
