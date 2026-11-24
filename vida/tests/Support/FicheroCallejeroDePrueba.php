<?php

namespace Tests\Support;

/**
 * Genera ficheros del callejero con el formato real del conjunto 200075 de datos.madrid.es.
 *
 * Reproduce lo comprobado el 24 nov 2026 sobre la versión 202611: 26 columnas, `;` como
 * separador, todos los campos entre comillas y rellenos de espacios, codificación
 * Windows-1252 (latin-1) y saltos de línea CRLF. Así los tests ejercitan el mismo
 * análisis que el fichero de verdad, sin descargarlo.
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class FicheroCallejeroDePrueba
{
    /** @var list<string> Cabecera real del fichero `callejero_vigente_numeraciones_AAAAMM.csv`. */
    public const CABECERA = [
        'Codigo de numero', 'Codigo de via', 'Clase de la via', 'Partícula de la vía', 'Nombre de la vía',
        'Literal de numeracion', 'Codigo de distrito', 'Nombre del distrito', 'Codigo de barrio',
        'Nombre del barrio', 'Seccion censal', 'Codigo postal', 'Seccion de carteria',
        'Zona Servicio Estacionamiento Regulado', 'Categoria fiscal', 'Direccion completa para el numero',
        'Coordenada X (Guia Urbana) cm', 'Coordenada Y (Guia Urbana) cm', 'Longitud en S R  ETRS89 WGS84',
        'Latitud en S R  ETRS89 WGS84', 'Tipo de la via a la que pertenece el numero',
        'Situacion de la via respecto al terreno', 'Tipo de denominacion de la via',
        'Parcela catastral del numero', 'Tipologia del numero', 'Zona de valor del numero',
    ];

    /**
     * Número de la calle Mayor 15 (distrito 01, barrio 6), con datos con la forma de los reales.
     *
     * @param array<string, string> $cambios Columnas que se sustituyen, por su nombre en la cabecera.
     *
     * @return array<string, string>
     */
    public static function fila(array $cambios = []): array
    {
        return array_merge([
            'Codigo de numero' => '20104713',
            'Codigo de via' => '471300',
            'Clase de la via' => 'CALLE',
            'Partícula de la vía' => '',
            'Nombre de la vía' => 'MAYOR',
            'Literal de numeracion' => 'NUM000015',
            'Codigo de distrito' => '01',
            'Nombre del distrito' => 'CENTRO',
            'Codigo de barrio' => '06',
            'Nombre del barrio' => 'SOL',
            'Seccion censal' => '028',
            'Codigo postal' => '28013',
            'Seccion de carteria' => '13',
            'Zona Servicio Estacionamiento Regulado' => '001',
            'Categoria fiscal' => '1',
            'Direccion completa para el numero' => 'MAYOR, 000015',
            'Coordenada X (Guia Urbana) cm' => '0044004000',
            'Coordenada Y (Guia Urbana) cm' => '0447435000',
            'Longitud en S R  ETRS89 WGS84' => "3º42'27.5'' W",
            'Latitud en S R  ETRS89 WGS84' => "40º24'54.12'' N",
            'Tipo de la via a la que pertenece el numero' => 'Vía',
            'Situacion de la via respecto al terreno' => 'Nivel',
            'Tipo de denominacion de la via' => 'Pleno',
            'Parcela catastral del numero' => '0045601VK4704F',
            'Tipologia del numero' => 'Portal',
            'Zona de valor del numero' => 'R01A',
        ], $cambios);
    }

    /**
     * Escribe un fichero con las filas indicadas y devuelve su ruta.
     *
     * @param list<array<string, string>> $filas Filas completas (ver fila()).
     * @param string $ruta Ruta del fichero que se crea (se sobrescribe).
     * @param list<string>|null $cabecera Cabecera alternativa (para simular un cambio de formato).
     *
     * @return string
     */
    public static function escribir(array $filas, string $ruta, ?array $cabecera = null): string
    {
        $cabecera ??= self::CABECERA;
        $lineas = [self::linea($cabecera, false)];

        foreach ($filas as $fila) {
            $lineas[] = self::linea(array_map(fn (string $columna) => $fila[$columna] ?? '', $cabecera), true);
        }

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        file_put_contents($ruta, mb_convert_encoding(implode("\r\n", $lineas)."\r\n", 'Windows-1252', 'UTF-8'));

        return $ruta;
    }

    /**
     * Compone una línea con comillas y, en los datos, el relleno de espacios del fichero real.
     *
     * @param list<string> $valores Valores de la línea, en orden.
     * @param bool $rellenar Si se rellena con espacios a la derecha, como hace el fichero real.
     *
     * @return string
     */
    private static function linea(array $valores, bool $rellenar): string
    {
        return implode(';', array_map(
            fn (string $valor) => '"'.($rellenar ? str_pad($valor, max(6, mb_strlen($valor) + 2)) : $valor).'"',
            $valores,
        ));
    }
}
