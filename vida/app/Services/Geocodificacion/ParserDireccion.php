<?php

namespace App\Services\Geocodificacion;

use App\Enums\TipoNumeracion;

/**
 * Analiza una dirección española escrita a mano: tipo de vía, nombre, número y calificador,
 * portal, escalera, piso, puerta, código postal y localidad.
 *
 * Solo reconoce la forma del texto; no comprueba que la dirección exista. Lo usan el
 * adaptador del callejero, que contrasta el resultado con el callejero oficial, y el
 * simulador de los tests. Antes vivía dentro del simulador (extraído el 24 nov 2026).
 *
 * Limitación conocida: el primer número del texto se toma como número de la vía, así que
 * un nombre con cifras («Cañada Real Sector 6», «26 de Abril») no se analiza bien. Esas
 * direcciones se eligen en el autocompletado del domicilio.
 *
 * Ver docs/geocodificacion.md § 5.1.
 */
class ParserDireccion
{
    /**
     * Tipos de vía reconocidos con sus variantes abreviadas.
     *
     * @var array<string, list<string>>
     */
    private const TIPOS_VIA = [
        'Calle' => ['calle', 'c/', 'c.', 'cl.', 'cl/', 'cl'],
        'Avenida' => ['avenida', 'avda.', 'avda', 'av.', 'av/', 'avd.'],
        'Plaza' => ['plaza', 'pza.', 'pza', 'pl.', 'plz.'],
        'Paseo' => ['paseo', 'pº', 'po.', 'po/', 'pso.'],
        'Carretera' => ['carretera', 'ctra.', 'ctra', 'ctr.'],
        'Ronda' => ['ronda', 'rda.', 'rda'],
        'Cañada' => ['cañada'],
        'Camino' => ['camino', 'cno.', 'cmno.'],
        'Travesía' => ['travesía', 'travesia', 'trav.', 'trv.', 'tsia.'],
        'Glorieta' => ['glorieta', 'glta.'],
        'Bulevar' => ['bulevar', 'blvr.', 'bvar.'],
        'Pasaje' => ['pasaje', 'pje.'],
        'Callejón' => ['callejón', 'callejon', 'cjon.'],
        'Costanilla' => ['costanilla'],
        'Cuesta' => ['cuesta'],
        'Colonia' => ['colonia'],
    ];

    /** Palabras que indican una parte del domicilio, no una localidad. */
    private const COMPLEMENTOS = '/\b(portal|esc(alera)?|piso|puerta|pta|bajo|bj|[áa]tico|entreplanta|entresuelo|izq(da)?|izda|dcha|der(echa)?|izquierda|local|bis|interior|exterior|ext|int)\b/iu';

    /**
     * Analiza el texto de una dirección.
     *
     * @param string $direccionTexto Texto libre tal como lo escribe el profesional.
     *
     * @return DireccionAnalizada
     */
    public function analizar(string $direccionTexto): DireccionAnalizada
    {
        $texto = trim($direccionTexto);

        // 1. Tipo de vía
        [$tipoVia, $restoTrasVia] = $this->extraerTipoVia($texto);

        // 2. Número (antes que el nombre: lo delimita)
        [$tipoNumeracion, $numero, $calificador, $restoTrasNumero, $posicionNumero] = $this->extraerNumero($restoTrasVia);

        // 3. Nombre de la vía: entre el tipo y el número
        $nombreVia = $this->extraerNombreVia($restoTrasVia, $posicionNumero);

        // 4. Piso, puerta y similares
        [$portal, $escalera, $piso, $puerta] = $this->extraerComplementos($restoTrasNumero);

        return new DireccionAnalizada(
            tipoVia: $tipoVia,
            nombreVia: $nombreVia,
            tipoNumeracion: $tipoNumeracion,
            numero: $numero,
            calificador: $calificador,
            portal: $portal,
            escalera: $escalera,
            piso: $piso,
            puerta: $puerta,
            // 5. Código postal y localidad
            codigoPostal: $this->extraerCodigoPostal($texto),
            localidad: $posicionNumero !== null ? $this->extraerLocalidad($restoTrasNumero) : null,
        );
    }

    /**
     * Extrae el tipo de vía del principio del texto.
     *
     * @param string $texto
     *
     * @return array{0: string|null, 1: string} Tipo (null si no se reconoce) y resto del texto.
     */
    private function extraerTipoVia(string $texto): array
    {
        $minusculas = mb_strtolower($texto);

        foreach (self::TIPOS_VIA as $nombreTipo => $variantes) {
            // Las variantes largas primero, para que «avda.» gane a «av.»
            usort($variantes, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

            foreach ($variantes as $variante) {
                // Tras una abreviatura con barra o punto puede no haber espacio («c/Mayor»)
                $separador = in_array(mb_substr($variante, -1), ['/', '.'], true) ? '\s*' : '\s+';

                if (preg_match('/^'.preg_quote($variante, '/').$separador.'/u', $minusculas, $m)) {
                    return [$nombreTipo, trim(mb_substr($texto, mb_strlen($m[0])))];
                }
            }
        }

        return [null, $texto];
    }

    /**
     * Extrae el número del texto que sigue al tipo de vía.
     *
     * Reconoce «s/n» y «sin número», los kilómetros y el número con «bis» o con una letra
     * pegada («15B»), que es el calificador del portal.
     *
     * @param string $texto Texto tras el tipo de vía.
     *
     * @return array{0: TipoNumeracion|null, 1: string|null, 2: string|null, 3: string, 4: int|null}
     *                                                                                               Tipo, número, calificador, resto y posición (en bytes) del número.
     */
    private function extraerNumero(string $texto): array
    {
        if (preg_match('/\bs\/n\b|\bsin\s+n[úu]mero\b/iu', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];

            return [TipoNumeracion::SinNumero, null, null, trim(substr($texto, $pos + strlen($m[0][0]))), $pos];
        }

        if (preg_match('/\bkm\.?\s*(\d+(?:[.,]\d+)?)/i', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];

            return [TipoNumeracion::Km, $m[1][0], null, trim(substr($texto, $pos + strlen($m[0][0]))), $pos];
        }

        // Número con «bis»/«duplicado», o con una letra pegada que no sea un ordinal de piso
        if (preg_match('/\b(\d+)(?:\s*(bis|dup(?:licado)?)\b|([a-zñ])(?![\p{L}ºª°]))?/iu', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $numero = $m[1][0].(! empty($m[2][0] ?? '') ? ' '.mb_strtolower($m[2][0]) : '');
            $calificador = ! empty($m[3][0] ?? '') ? mb_strtoupper($m[3][0]) : null;

            return [TipoNumeracion::Numero, $numero, $calificador, trim(substr($texto, $pos + strlen($m[0][0]))), $pos];
        }

        return [null, null, null, $texto, null];
    }

    /**
     * Extrae el nombre de la vía (lo que hay entre el tipo y el número).
     *
     * @param string $texto Texto tras el tipo de vía.
     * @param int|null $posicionNumero Posición en bytes donde empieza el número.
     *
     * @return string
     */
    private function extraerNombreVia(string $texto, ?int $posicionNumero): string
    {
        if ($posicionNumero === null) {
            return trim(rtrim(trim($texto), ','));
        }

        return trim(rtrim(trim(substr($texto, 0, $posicionNumero)), ', -'));
    }

    /**
     * Extrae portal, escalera, piso y puerta del texto que sigue al número.
     *
     * @param string $texto Texto tras el número.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string|null}
     */
    private function extraerComplementos(string $texto): array
    {
        $portal = $escalera = $piso = $puerta = null;

        if (trim($texto) === '') {
            return [$portal, $escalera, $piso, $puerta];
        }

        if (preg_match('/\bportal\s+(\w+)/iu', $texto, $m)) {
            $portal = $m[1];
        }

        if (preg_match('/\besc(?:alera)?\.?\s+(\w+)/iu', $texto, $m)) {
            $escalera = $m[1];
        }

        if (preg_match('/\bpiso\s+(\w+)/iu', $texto, $m)) {
            $piso = $m[1];
        } elseif (preg_match('/\b(\d+)\s*[ºª°]/u', $texto, $m)) {
            $piso = $m[1];
        } elseif (preg_match('/\b(bajo|bj)\b/iu', $texto)) {
            $piso = 'bajo';
        } elseif (preg_match('/\b([áa]tico)\b/iu', $texto)) {
            $piso = 'ático';
        }

        if (preg_match('/\b(izq(?:da?)?|dcha?|izda?)\b/iu', $texto, $m)) {
            $puerta = mb_strtolower($m[1]);
        } elseif (preg_match('/\b\d+\s*[ºª°]\s*([a-z])\b/iu', $texto, $m)) {
            // Letra tras el piso: «2º B»
            $puerta = mb_strtoupper($m[1]);
        } elseif (preg_match('/,\s*([a-z])\s*(?:$|,)/iu', $texto, $m)) {
            $puerta = mb_strtoupper($m[1]);
        }

        return [$portal, $escalera, $piso, $puerta];
    }

    /**
     * Extrae el código postal de Madrid (cinco cifras que empiezan por 28).
     *
     * @param string $texto Texto completo.
     *
     * @return string|null
     */
    private function extraerCodigoPostal(string $texto): ?string
    {
        return preg_match('/\b(28\d{3})\b/', $texto, $m) ? $m[1] : null;
    }

    /**
     * Localidad escrita tras el número: un tramo entre comas solo con letras que no es
     * una parte del domicilio («…, 15, 2º B, Alcalá de Henares» → «Alcalá de Henares»).
     *
     * @param string $texto Texto tras el número.
     *
     * @return string|null
     */
    private function extraerLocalidad(string $texto): ?string
    {
        foreach (explode(',', $texto) as $tramo) {
            // Sin el código postal, que suele ir pegado a la localidad («28801 Alcalá»)
            $tramo = trim((string) preg_replace('/\b\d{5}\b/', '', $tramo));

            if (preg_match('/^\p{L}[\p{L} .\'-]{2,}$/u', $tramo) === 1 && preg_match(self::COMPLEMENTOS, $tramo) !== 1) {
                return $tramo;
            }
        }

        return null;
    }
}
