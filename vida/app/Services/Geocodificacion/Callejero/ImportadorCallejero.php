<?php

namespace App\Services\Geocodificacion\Callejero;

use App\Enums\TipoNumeracion;
use App\Enums\TipoNumeroCallejero;
use App\Models\Callejero\CallejeroImportacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga el fichero de numeraciones del callejero oficial en `callejero_portales`.
 *
 * Sustituye la copia entera dentro de una transacción: si las cabeceras han cambiado o
 * hay demasiadas filas ilegibles, la tabla se queda como estaba. Lee el fichero por
 * líneas para no cargar en memoria sus 215.000 filas.
 *
 * Formato comprobado el 24 nov 2026 (versión 202611): `;`, comillas, relleno de
 * espacios, Windows-1252 y CRLF. Ver «Hechos que no hay que redescubrir» en
 * docs/instrucciones-cli/2026-11-callejero-madrid.md.
 */
class ImportadorCallejero
{
    /** @var list<string> Columnas que se usan; si falta alguna, el fichero ha cambiado de formato. */
    public const COLUMNAS = [
        'Codigo de numero', 'Codigo de via', 'Clase de la via', 'Partícula de la vía', 'Nombre de la vía',
        'Literal de numeracion', 'Codigo de distrito', 'Codigo de barrio', 'Seccion censal', 'Codigo postal',
        'Direccion completa para el numero', 'Longitud en S R  ETRS89 WGS84', 'Latitud en S R  ETRS89 WGS84',
        'Tipologia del numero',
    ];

    /** Filas por cada INSERT. */
    private const LOTE = 1000;

    /** Código INE de provincia y municipio de Madrid. */
    private const PREFIJO_INE = '28079';

    /**
     * Importa el fichero y deja constancia de la carga.
     *
     * @param string $ruta Fichero CSV local.
     * @param string $version AAAAMM del fichero, o «local».
     * @param string $origen URL de la que se descargó, o la ruta local.
     *
     * @return CallejeroImportacion
     *
     * @throws ImportacionCallejeroException Si el fichero no es válido; la tabla no cambia.
     */
    public function importar(string $ruta, string $version, string $origen): CallejeroImportacion
    {
        $fichero = @fopen($ruta, 'rb');

        if ($fichero === false) {
            throw new ImportacionCallejeroException("No se puede leer el fichero {$ruta}.");
        }

        try {
            [$cabecera, $esUtf8] = $this->leerCabecera($fichero);

            return DB::transaction(fn () => $this->sustituir($fichero, $cabecera, $esUtf8, $version, $origen));
        } finally {
            fclose($fichero);
        }
    }

    /**
     * Lee y valida la cabecera; detecta la codificación del fichero.
     *
     * @param resource $fichero
     *
     * @return array{0: list<string>, 1: bool} Columnas y si el fichero viene en UTF-8.
     *
     * @throws ImportacionCallejeroException Si faltan columnas.
     */
    private function leerCabecera($fichero): array
    {
        $linea = fgets($fichero);

        if ($linea === false) {
            throw new ImportacionCallejeroException('El fichero del callejero está vacío.');
        }

        $linea = preg_replace('/^\xEF\xBB\xBF/', '', $linea);
        $esUtf8 = mb_check_encoding($linea, 'UTF-8');
        $cabecera = array_map(
            fn (string $columna) => trim($this->aUtf8($columna, $esUtf8)),
            str_getcsv(rtrim($linea, "\r\n"), ';', '"', ''),
        );

        $faltan = array_diff(self::COLUMNAS, $cabecera);

        if ($faltan !== []) {
            throw new ImportacionCallejeroException('El formato del callejero ha cambiado. Faltan las columnas: '.implode(', ', $faltan).'.');
        }

        return [$cabecera, $esUtf8];
    }

    /**
     * Vacía la tabla y la rellena con el fichero (se ejecuta dentro de la transacción).
     *
     * @param resource $fichero Posicionado tras la cabecera.
     * @param list<string> $cabecera
     *
     * @return CallejeroImportacion
     *
     * @throws ImportacionCallejeroException Si hay demasiadas filas ilegibles (deshace la transacción).
     */
    private function sustituir($fichero, array $cabecera, bool $esUtf8, string $version, string $origen): CallejeroImportacion
    {
        $ahora = now();
        $leidas = 0;
        $descartadas = 0;
        $importadas = 0;
        $lote = [];

        DB::table('callejero_portales')->delete();

        while (($campos = fgetcsv($fichero, 0, ';', '"', '')) !== false) {
            // Línea en blanco (p. ej. al final del fichero)
            if ($campos === [null]) {
                continue;
            }

            $leidas++;
            $fila = count($campos) === count($cabecera)
                ? $this->interpretar(array_combine($cabecera, array_map(fn ($v) => $this->aUtf8((string) $v, $esUtf8), $campos)), $ahora)
                : null;

            if ($fila === null) {
                $descartadas++;

                continue;
            }

            $lote[] = $fila;

            if (count($lote) === self::LOTE) {
                DB::table('callejero_portales')->insert($lote);
                $importadas += count($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('callejero_portales')->insert($lote);
            $importadas += count($lote);
        }

        $maximo = (float) config('geocodificacion.callejero.max_descartados', 0.01);

        if ($importadas === 0 || $descartadas / max(1, $leidas) > $maximo) {
            throw new ImportacionCallejeroException("No se importa el callejero: {$descartadas} de {$leidas} filas no se pueden interpretar. Puede que haya cambiado el formato.");
        }

        return CallejeroImportacion::create([
            'version' => $version,
            'origen' => mb_substr($origen, 0, 500),
            'numeros' => $importadas,
            'descartados' => $descartadas,
            'importado_en' => $ahora,
        ]);
    }

    /**
     * Convierte una fila del fichero en una fila de `callejero_portales`, o null si no se entiende.
     *
     * @param array<string, string> $f Fila por nombre de columna, ya en UTF-8.
     * @param Carbon $ahora Momento de la importación.
     *
     * @return array<string, mixed>|null
     */
    private function interpretar(array $f, Carbon $ahora): ?array
    {
        $f = array_map('trim', $f);
        $numero = $this->numero($f['Literal de numeracion']);
        $lng = $this->grados($f['Longitud en S R  ETRS89 WGS84']);
        $lat = $this->grados($f['Latitud en S R  ETRS89 WGS84']);

        if ($f['Codigo de numero'] === '' || $f['Codigo de via'] === '' || $f['Nombre de la vía'] === ''
            || $numero === null || $lng === false || $lat === false
            || ! ctype_digit($f['Codigo de distrito']) || ! ctype_digit($f['Codigo de barrio'])) {
            return null;
        }

        $distrito = sprintf('%02d', (int) $f['Codigo de distrito']);
        $seccion = ctype_digit($f['Seccion censal']) ? (int) $f['Seccion censal'] : 0;

        return [
            'cod_ndp' => $f['Codigo de numero'],
            'cod_via' => $f['Codigo de via'],
            'clase_via' => $f['Clase de la via'],
            'particula_via' => $f['Partícula de la vía'] !== '' ? $f['Partícula de la vía'] : null,
            'nombre_via' => $f['Nombre de la vía'],
            'nombre_via_mostrar' => $this->nombreConTildes($f['Nombre de la vía'], $f['Direccion completa para el numero']),
            'nombre_via_busqueda' => TextoBusqueda::normalizar($f['Nombre de la vía']),
            'clase_numero' => $numero['clase']->value,
            'numero' => $numero['numero'],
            'calificador' => $numero['calificador'],
            'tipo_numero' => TipoNumeroCallejero::desdeTipologia($f['Tipologia del numero'])->value,
            'codigo_postal' => preg_match('/^\d{5}$/', $f['Codigo postal']) === 1 ? $f['Codigo postal'] : null,
            'distrito_codigo' => $distrito,
            // El número de barrio solo es único dentro de su distrito (catálogo: `016`)
            'barrio_codigo' => $distrito.(int) $f['Codigo de barrio'],
            // La sección 000 no es una sección real (cárcel provisional, una parcela de la Cañada Real)
            'seccion_censal_codigo' => $seccion > 0 ? self::PREFIJO_INE.$distrito.sprintf('%03d', $seccion) : null,
            'lat' => $lat,
            'lng' => $lng,
            'importado_en' => $ahora,
        ];
    }

    /**
     * Interpreta el literal del número: `NUM000015`, `NUM000076 B`, `NUM000008 Ñ`, `KM.001000EN`.
     *
     * @param string $literal
     *
     * @return array{clase: TipoNumeracion, numero: string, calificador: string|null}|null
     */
    private function numero(string $literal): ?array
    {
        // La Ñ también es calificador (8 números en la versión 202611)
        if (preg_match('/^(NUM|KM\.?)\s*(\d+)\s*([A-ZÑ0-9]*)$/u', $literal, $m) !== 1) {
            return null;
        }

        return [
            'clase' => $m[1] === 'NUM' ? TipoNumeracion::Numero : TipoNumeracion::Km,
            'numero' => ltrim($m[2], '0') !== '' ? ltrim($m[2], '0') : '0',
            'calificador' => $m[3] !== '' ? $m[3] : null,
        ];
    }

    /**
     * Convierte grados, minutos y segundos (`3º42'27.5'' W`) a grados decimales WGS84.
     *
     * @param string $texto
     *
     * @return float|false|null Null si viene vacío; false si no se entiende.
     */
    private function grados(string $texto): float|false|null
    {
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*[º°]\s*(\d+)\s*\'\s*([\d.]+)\s*(?:\'\'|")\s*([NSEW])$/u', $texto, $m) !== 1) {
            return false;
        }

        $valor = (int) $m[1] + (int) $m[2] / 60 + (float) $m[3] / 3600;

        return round(in_array($m[4], ['S', 'W'], true) ? -$valor : $valor, 7);
    }

    /**
     * Nombre de la vía con tildes, tomado de la dirección completa del número.
     *
     * La dirección completa lleva delante la clase abreviada («AVDA ALBUFERA, 000120») y
     * detrás el número; el nombre sin tildes del fichero tiene el mismo número de letras,
     * así que basta con tomar el final del texto antes de la coma y comprobar que casa.
     *
     * @param string $nombre Nombre sin tildes («ABAD JUAN CATALAN»).
     * @param string $direccionCompleta «ABAD JUAN CATALÁN, 000001».
     *
     * @return string El nombre con tildes, o el nombre sin tildes si no se puede deducir.
     */
    private function nombreConTildes(string $nombre, string $direccionCompleta): string
    {
        $coma = mb_strrpos($direccionCompleta, ',');
        $previo = trim($coma === false ? $direccionCompleta : mb_substr($direccionCompleta, 0, $coma));
        $largo = mb_strlen($nombre);
        $candidato = mb_substr($previo, -$largo);
        $separado = mb_strlen($previo) === $largo || mb_substr($previo, -$largo - 1, 1) === ' ';

        return $separado && TextoBusqueda::normalizar($candidato) === TextoBusqueda::normalizar($nombre)
            ? $candidato
            : $nombre;
    }

    /**
     * Pasa un valor del fichero a UTF-8 (el portal publica en Windows-1252).
     *
     * @param string $valor
     * @param bool $esUtf8 Si el fichero ya viene en UTF-8.
     *
     * @return string
     */
    private function aUtf8(string $valor, bool $esUtf8): string
    {
        return $esUtf8 ? $valor : mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
    }
}
