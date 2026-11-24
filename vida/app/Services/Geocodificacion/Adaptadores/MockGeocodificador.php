<?php

namespace App\Services\Geocodificacion\Adaptadores;

use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ParserDireccion;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Closure;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Adaptador de geocodificación para desarrollo y pruebas.
 *
 * Analiza el texto con ParserDireccion (el mismo que usa el adaptador del callejero)
 * y lo da por bueno, con coordenadas aleatorias dentro del bounding box del
 * municipio de Madrid. Solo se usa en los tests.
 *
 * Los códigos territoriales (NDP, distrito, barrio y sección) son coherentes con
 * el catálogo sembrado: la sección se elige de forma determinista a partir del
 * portal (vía + número), de modo que la misma dirección cae siempre en la misma
 * sección y dos personas del mismo portal comparten NDP y sección. Si el catálogo
 * está vacío, los códigos quedan a null.
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

    /** @var Closure(string, int): (SeccionCensal|null) Elige la sección para una clave de portal y su hash. */
    private readonly Closure $elegirSeccion;

    /**
     * @param Closure(string, int): (SeccionCensal|null)|null $elegirSeccion Cómo se elige la sección
     *                                                                       de un portal. Por defecto, del catálogo de la BD; el test del parser pasa uno sin catálogo
     *                                                                       para probar el texto sin base de datos.
     * @param ParserDireccion $parser Análisis del texto, compartido con el adaptador del callejero.
     */
    public function __construct(?Closure $elegirSeccion = null, private readonly ParserDireccion $parser = new ParserDireccion)
    {
        $this->elegirSeccion = $elegirSeccion ?? fn (string $clave, int $hash) => $this->seccionDelCatalogo($hash);
    }

    /** Bbox aproximado del municipio de Madrid (WGS84). */
    private const LAT_MIN = 40.31;

    private const LAT_MAX = 40.53;

    private const LNG_MIN = -3.83;

    private const LNG_MAX = -3.52;

    // -------------------------------------------------------------------------
    // Interfaz pública
    // -------------------------------------------------------------------------

    /**
     * Normaliza una dirección en texto libre.
     *
     * Analiza el texto (docs/geocodificacion.md § 5.1) y genera coordenadas
     * aleatorias dentro del bbox de Madrid.
     *
     * @param string $direccionTexto Texto libre.
     *
     * @return ResultadoGeocodificacion Siempre devuelve exito = true.
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        $texto = trim($direccionTexto);
        $analisis = $this->parser->analizar($texto);

        // Sin tipo de vía reconocido, el simulador supone «Calle»
        $tipoVia = $analisis->tipoVia ?? 'Calle';
        $nombreVia = $analisis->nombreVia;
        $numero = $analisis->numero;
        $codigoPostal = $analisis->codigoPostal;

        // Coordenadas aleatorias dentro del bbox de Madrid
        [$latitud, $longitud] = $this->coordenadasAleatorias();

        // Códigos territoriales deterministas por portal
        $clavePortal = $this->clavePortal($texto, $tipoVia, $nombreVia, $numero);
        $seccion = $this->seccionDeterminista($clavePortal);

        return new ResultadoGeocodificacion(
            exito: true,
            tipoVia: $tipoVia,
            nombreVia: $nombreVia ?: null,
            tipoNumeracion: $analisis->tipoNumeracion,
            numero: $numero,
            portal: $analisis->portal,
            escalera: $analisis->escalera,
            piso: $analisis->piso,
            puerta: $analisis->puerta,
            codigoPostal: $codigoPostal,
            municipio: $codigoPostal ? 'Madrid' : null,
            latitud: $latitud,
            longitud: $longitud,
            proveedor: self::IDENTIFICADOR,
            codigoNdp: $seccion ? 'MOCK'.strtoupper(substr(md5($clavePortal), 0, 12)) : null,
            codigoDistrito: $seccion?->distrito?->codigo,
            codigoBarrio: $seccion?->barrio?->codigo,
            seccionCensal: $seccion?->codigo_ine,
        );
    }

    /**
     * Clave que identifica el portal: vía y número, sin piso ni puerta. Si el
     * parser no extrajo la vía, se usa el texto completo.
     *
     * @param string $texto Texto original.
     * @param string|null $tipoVia Tipo de vía extraído.
     * @param string|null $nombreVia Nombre de vía extraído.
     * @param string|null $numero Número extraído.
     *
     * @return string
     */
    private function clavePortal(string $texto, ?string $tipoVia, ?string $nombreVia, ?string $numero): string
    {
        $clave = $nombreVia ? implode('|', [$tipoVia, $nombreVia, $numero]) : $texto;

        return mb_strtolower(preg_replace('/\s+/', ' ', trim($clave)));
    }

    /**
     * Elige una sección censal en función de la clave del portal: el mismo
     * portal da siempre la misma sección mientras no cambie el catálogo.
     *
     * @param string $clavePortal Clave del portal.
     *
     * @return SeccionCensal|null
     */
    private function seccionDeterminista(string $clavePortal): ?SeccionCensal
    {
        return ($this->elegirSeccion)($clavePortal, crc32($clavePortal));
    }

    /**
     * Sección activa del catálogo en la posición que marca el hash del portal.
     *
     * @param int $hash Hash de la clave del portal.
     *
     * @return SeccionCensal|null Null si el catálogo está vacío.
     */
    private function seccionDelCatalogo(int $hash): ?SeccionCensal
    {
        $total = SeccionCensal::activas()->count();

        if ($total === 0) {
            return null;
        }

        return SeccionCensal::activas()
            ->with(['distrito', 'barrio'])
            ->orderBy('codigo_ine')
            ->offset($hash % $total)
            ->first();
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
