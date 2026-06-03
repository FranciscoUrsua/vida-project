<?php

namespace App\Services\Geocodificacion\Adaptadores;

use App\Enums\TipoNumeracion;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ResultadoGeocodificacion;

/**
 * Adaptador de geocodificación para desarrollo y pruebas.
 *
 * Implementa un parser de texto libre con reglas para extraer los campos
 * estructurados de una dirección española, más coordenadas aleatorias dentro
 * del bounding box del municipio de Madrid.
 *
 * No valida que la dirección exista realmente ni calcula coordenadas precisas.
 * Su objetivo es que toda la lógica que consume ResultadoGeocodificacion funcione
 * correctamente sin depender de ningún servicio externo.
 *
 * Ver docs/geocodificacion.md § 5.
 */
class MockGeocodificador implements GeocodificadorInterface
{
    private const IDENTIFICADOR = 'mock';

    /** Bbox aproximado del municipio de Madrid (WGS84). */
    private const LAT_MIN = 40.31;

    private const LAT_MAX = 40.53;

    private const LNG_MIN = -3.83;

    private const LNG_MAX = -3.52;

    /**
     * Tipos de vía reconocidos con sus variantes abreviadas.
     *
     * @var array<string, list<string>>
     */
    private const TIPOS_VIA = [
        'Calle' => ['calle', 'c/', 'c.', 'cl.', 'cl/'],
        'Avenida' => ['avenida', 'avda.', 'avda', 'av.', 'av/'],
        'Plaza' => ['plaza', 'pza.', 'pza', 'pl.'],
        'Paseo' => ['paseo', 'pº', 'po.', 'po/'],
        'Carretera' => ['carretera', 'ctra.', 'ctra', 'ctr.'],
        'Ronda' => ['ronda', 'rda.', 'rda'],
        'Cañada' => ['cañada'],
        'Camino' => ['camino', 'cno.'],
        'Travesía' => ['travesía', 'trav.', 'trv.'],
        'Glorieta' => ['glorieta', 'glta.'],
        'Bulevar' => ['bulevar', 'blvr.'],
    ];

    // -------------------------------------------------------------------------
    // Interfaz pública
    // -------------------------------------------------------------------------

    /**
     * Normaliza una dirección en texto libre.
     *
     * Aplica el parser de 5 pasos descrito en docs/geocodificacion.md § 5.1
     * y genera coordenadas aleatorias dentro del bbox de Madrid.
     *
     * @param string $direccionTexto Texto libre.
     *
     * @return ResultadoGeocodificacion Siempre devuelve exito = true.
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        $texto = trim($direccionTexto);

        // Paso 1 — Tipo de vía
        [$tipoVia, $restoTrasVia] = $this->extraerTipoVia($texto);

        // Paso 2 — Número (necesario antes del nombre para delimitar la vía)
        [$tipoNumeracion, $numero, $restoTrasNumero, $posicionNumero] = $this->extraerNumero($restoTrasVia);

        // Paso 3 — Nombre de vía (entre el tipo de vía y el número)
        $nombreVia = $this->extraerNombreVia($restoTrasVia, $posicionNumero);

        // Paso 4 — Resto: piso, puerta y similares
        [$portal, $escalera, $piso, $puerta] = $this->extraerComplementos($restoTrasNumero);

        // Paso 5 — Código postal (5 dígitos empezando por 28 para Madrid)
        $codigoPostal = $this->extraerCodigoPostal($texto);

        // Coordenadas aleatorias dentro del bbox de Madrid
        [$latitud, $longitud] = $this->coordenadasAleatorias();

        return new ResultadoGeocodificacion(
            exito: true,
            tipoVia: $tipoVia,
            nombreVia: $nombreVia ?: null,
            tipoNumeracion: $tipoNumeracion,
            numero: $numero,
            portal: $portal,
            escalera: $escalera,
            piso: $piso,
            puerta: $puerta,
            codigoPostal: $codigoPostal,
            municipio: $codigoPostal ? 'Madrid' : null,
            latitud: $latitud,
            longitud: $longitud,
            proveedor: self::IDENTIFICADOR,
        );
    }

    // -------------------------------------------------------------------------
    // Parser — pasos 1 a 5
    // -------------------------------------------------------------------------

    /**
     * Paso 1: extrae el tipo de vía del inicio del texto.
     *
     * Si no se reconoce ningún prefijo, devuelve 'Calle' como valor por defecto
     * y el texto completo como resto.
     *
     * @return array{string, string} [$tipoVia, $restoTrasVia]
     */
    private function extraerTipoVia(string $texto): array
    {
        $textoNormalizado = mb_strtolower($texto);

        foreach (self::TIPOS_VIA as $nombreTipo => $variantes) {
            // Ordenar por longitud descendente para que "avda." se reconozca antes que "av."
            usort($variantes, fn ($a, $b) => strlen($b) - strlen($a));

            foreach ($variantes as $variante) {
                $patron = '/^'.preg_quote($variante, '/').'\s+/i';
                if (preg_match($patron, $textoNormalizado, $m)) {
                    $resto = mb_substr($texto, mb_strlen($m[0]));

                    return [$nombreTipo, trim($resto)];
                }
            }
        }

        // Sin prefijo reconocido → Calle por defecto
        return ['Calle', $texto];
    }

    /**
     * Paso 2: extrae el número del texto restante tras el tipo de vía.
     *
     * Reconoce "s/n" y "sin número/sin numero" como sin_numero.
     * El número puede ir seguido de sufijos como "bis", "duplicado".
     *
     * @param string $texto Texto tras el tipo de vía.
     *
     * @return array{TipoNumeracion|null, string|null, string, int|null}
     *                                                                   [$tipoNumeracion, $numero, $restoTrasNumero, $posicionNumeroEnTexto]
     */
    private function extraerNumero(string $texto): array
    {
        // Sin número explícito
        if (preg_match('/\bs\/n\b|\bsin\s+n[úu]mero\b/iu', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $resto = trim(substr($texto, $pos + strlen($m[0][0])));

            return [TipoNumeracion::SinNumero, null, $resto, $pos];
        }

        // Kilómetro
        if (preg_match('/\bkm\.?\s*(\d+(?:[.,]\d+)?)/i', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $resto = trim(substr($texto, $pos + strlen($m[0][0])));

            return [TipoNumeracion::Km, $m[1][0], $resto, $pos];
        }

        // Número ordinal de portal: dígito(s) opcionalmente seguido de bis/dup
        if (preg_match('/\b(\d+\s*(?:bis|dup(?:licado)?)?)\b/i', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $numeroLimpio = trim($m[1][0]);
            $resto = trim(substr($texto, $pos + strlen($m[0][0])));

            return [TipoNumeracion::Numero, $numeroLimpio, $resto, $pos];
        }

        return [null, null, $texto, null];
    }

    /**
     * Paso 3: extrae el nombre de la vía (texto entre el tipo y el número).
     *
     * @param string $texto Texto tras el tipo de vía.
     * @param int|null $posicionNumero Posición en bytes donde empieza el número.
     */
    private function extraerNombreVia(string $texto, ?int $posicionNumero): string
    {
        if ($posicionNumero === null) {
            // Sin número: todo el texto es el nombre de la vía (quitando coma final)
            return trim(rtrim(trim($texto), ','));
        }

        $nombre = trim(substr($texto, 0, $posicionNumero));

        // Limpiar delimitadores frecuentes: coma, guion
        return trim(rtrim($nombre, ', -'));
    }

    /**
     * Paso 4: extrae piso, puerta y similares del texto restante tras el número.
     *
     * @param string $texto Texto tras el número.
     *
     * @return array{string|null, string|null, string|null, string|null}
     *                                                                   [$portal, $escalera, $piso, $puerta]
     */
    private function extraerComplementos(string $texto): array
    {
        $portal = null;
        $escalera = null;
        $piso = null;
        $puerta = null;

        if (empty(trim($texto))) {
            return [$portal, $escalera, $piso, $puerta];
        }

        // Portal: "portal N" o "pta. N" o "pt. N"
        if (preg_match('/\bportal\s+(\w+)/i', $texto, $m)) {
            $portal = $m[1];
        }

        // Escalera: "esc. N" o "escalera N" o "esc N"
        if (preg_match('/\besc(?:alera)?\.?\s+(\w+)/i', $texto, $m)) {
            $escalera = $m[1];
        }

        // Piso: número seguido de º/ª, o "piso N", o dígito solo antes de izq/dcha/letra
        if (preg_match('/\bpiso\s+(\w+)/i', $texto, $m)) {
            $piso = $m[1];
        } elseif (preg_match('/\b(\d+)[ºª°]/u', $texto, $m)) {
            $piso = $m[1];
        }

        // Puerta: izq/dcha/izda o letra sola o número después del piso
        if (preg_match('/\b(izq(?:da?)?|dcha?|izda?)\b/i', $texto, $m)) {
            $puerta = mb_strtolower($m[1]);
        } elseif (preg_match('/,\s*([A-Za-z])\s*(?:$|,)/u', $texto, $m)) {
            $puerta = mb_strtoupper($m[1]);
        }

        return [$portal, $escalera, $piso, $puerta];
    }

    /**
     * Paso 5: extrae el código postal de 5 dígitos empezando por 28 (Madrid).
     *
     * @param string $texto Texto completo original.
     */
    private function extraerCodigoPostal(string $texto): ?string
    {
        if (preg_match('/\b(28\d{3})\b/', $texto, $m)) {
            return $m[1];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Coordenadas
    // -------------------------------------------------------------------------

    /**
     * Genera coordenadas aleatorias dentro del bbox del municipio de Madrid.
     *
     * @return array{float, float} [$latitud, $longitud]
     */
    private function coordenadasAleatorias(): array
    {
        $latitud = self::LAT_MIN + (mt_rand() / mt_getrandmax()) * (self::LAT_MAX - self::LAT_MIN);
        $longitud = self::LNG_MIN + (mt_rand() / mt_getrandmax()) * (self::LNG_MAX - self::LNG_MIN);

        return [round($latitud, 7), round($longitud, 7)];
    }
}
