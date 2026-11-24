<?php

namespace App\Services\Geocodificacion\Callejero;

use Illuminate\Support\Facades\Http;

/**
 * Localiza y descarga la versión vigente del fichero de numeraciones del callejero.
 *
 * El nombre del fichero cambia cada mes (`callejero_vigente_numeraciones_AAAAMM.csv`),
 * así que se pregunta a la API CKAN de datos.madrid.es por el recurso del conjunto
 * configurado. Solo se descargan datos públicos: no se envía nada de VIDA.
 *
 * @see config/geocodificacion.php
 */
class FuenteCallejero
{
    /**
     * URL y versión del fichero vigente según el portal de datos abiertos.
     *
     * @return array{url: string, version: string}
     *
     * @throws ImportacionCallejeroException Si el portal no devuelve el recurso.
     */
    public function recursoVigente(): array
    {
        $config = config('geocodificacion.callejero');

        $respuesta = Http::timeout(30)->acceptJson()
            ->get($config['api_ckan'], ['id' => $config['conjunto']]);

        if (! $respuesta->successful() || $respuesta->json('success') !== true) {
            throw new ImportacionCallejeroException("El portal de datos abiertos no responde para el conjunto {$config['conjunto']} (HTTP {$respuesta->status()}).");
        }

        $recurso = collect($respuesta->json('result.resources', []))->firstWhere('id', $config['recurso']);

        if (empty($recurso['url'])) {
            throw new ImportacionCallejeroException("El conjunto {$config['conjunto']} ya no tiene el recurso {$config['recurso']}.");
        }

        return ['url' => $recurso['url'], 'version' => self::versionDe($recurso['url'])];
    }

    /**
     * Descarga el fichero al destino indicado, sin cargarlo en memoria.
     *
     * @param string $url URL del recurso.
     * @param string $destino Ruta local donde se guarda.
     *
     * @return void
     *
     * @throws ImportacionCallejeroException Si la descarga falla.
     */
    public function descargar(string $url, string $destino): void
    {
        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        $respuesta = Http::timeout((int) config('geocodificacion.callejero.timeout_descarga'))
            ->sink($destino)
            ->get($url);

        if (! $respuesta->successful()) {
            throw new ImportacionCallejeroException("No se pudo descargar el callejero (HTTP {$respuesta->status()}): {$url}");
        }
    }

    /**
     * Versión (AAAAMM) que indica el nombre del fichero, o «local» si no la lleva.
     *
     * @param string $nombre Nombre o URL del fichero.
     *
     * @return string
     */
    public static function versionDe(string $nombre): string
    {
        return preg_match('/_(\d{6})\.csv$/i', $nombre, $m) === 1 ? $m[1] : 'local';
    }
}
