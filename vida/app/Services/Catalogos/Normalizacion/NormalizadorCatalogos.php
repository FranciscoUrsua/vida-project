<?php

namespace App\Services\Catalogos\Normalizacion;

use App\Services\Catalogos\CargaCatalogosOficiales;
use Collator;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Convierte los ficheros oficiales de `database/data/catalogos/fuentes/` en los CSV
 * normalizados que carga `CargaCatalogosOficiales` (bloque A de la ficha sociodemográfica).
 *
 * Regla de los datos oficiales: todo valor sale de un fichero descargado; aquí no se
 * escribe ninguna lista de países, idiomas o niveles a mano. Lo único que decide este
 * código es qué columnas se toman y cómo se agrupan, y eso está comentado en cada método.
 *
 * Lo usa `php artisan catalogos:normalizar`; no se ejecuta al desplegar.
 */
class NormalizadorCatalogos
{
    /** Eurostat usa sus propios códigos donde difieren de ISO 3166-1 alfa-2. */
    private const EUROSTAT_A_ISO2 = ['EL' => 'GR'];

    /** @var array<string, array<string, string>> Manifiesto de `fuentes/fuentes.json`. */
    private array $manifiesto;

    /**
     * @param string $fuentes Carpeta de los ficheros originales y de `fuentes.json`.
     *
     * @throws RuntimeException Si falta el manifiesto.
     */
    public function __construct(private readonly string $fuentes)
    {
        $ruta = $this->ruta('fuentes.json');
        $this->manifiesto = json_decode((string) file_get_contents($ruta), true)
            ?? throw new RuntimeException("{$ruta} no es JSON válido.");
    }

    /**
     * Países: tabla en español de M49 (clave ISO alfa-3) con la marca UE de Eurostat.
     *
     * @return list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}>
     *
     * @throws RuntimeException Si la tabla no aparece o un código de Eurostat no está en M49.
     */
    public function paises(): array
    {
        $ue = $this->estadosMiembrosUe();
        $filas = [];

        foreach ($this->tablaM49() as $pais) {
            $iso2 = $pais['ISO-alpha2 Code'];
            $filas[] = [
                'clave' => $pais['ISO-alpha3 Code'],
                'etiqueta' => $pais['Country or Area'],
                'orden' => 0,
                'atributos' => array_filter([
                    'region' => $pais['Region Name'],
                    'region_m49' => $pais['Region Code'],
                    'subregion' => $pais['Sub-region Name'],
                    'subregion_m49' => $pais['Sub-region Code'],
                    'region_intermedia' => $pais['Intermediate Region Name'],
                    'region_intermedia_m49' => $pais['Intermediate Region Code'],
                ], fn (string $v) => $v !== '') + ['ue' => in_array($iso2, $ue, true)],
                // `ine` y `hsu` quedan vacías hasta tener sus fuentes (BACKLOG)
                'equivalencias' => ['iso2' => $iso2, 'm49' => $pais['M49 Code'], 'ine' => null, 'hsu' => null],
                'fuente' => $this->fuente('m49_overview.html'),
            ];
        }

        $faltan = array_diff($ue, array_column(array_column($filas, 'equivalencias'), 'iso2'));
        if ($faltan !== []) {
            throw new RuntimeException('Estados de la UE de Eurostat que no están en M49: '.implode(', ', $faltan));
        }

        return $this->ordenarPorEtiqueta($filas);
    }

    /**
     * Niveles de formación alcanzados: CNED-A 2014 con su correspondencia CINE-A 2011.
     *
     * La clave es el código de dos cifras (el nivel más detallado). `agrupacion` es el
     * nivel de una cifra y `etapa`, la letra (A, B, C) de la propia CNED.
     *
     * @return list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}>
     *
     * @throws RuntimeException Si falta la correspondencia de algún nivel.
     */
    public function nivelesEstudios(): array
    {
        $lector = new LectorXlsx;
        $cine = [];
        foreach ($lector->filas($this->ruta('CORR_CNED14_CINE11_A.xls')) as $fila) {
            if (preg_match('/^\d{3}$/', trim($fila[0] ?? '')) && preg_match('/^\d{2}$/', trim($fila[1] ?? ''))) {
                $cine[trim($fila[1])] = trim($fila[0]);
            }
        }

        $filas = [];
        $etapa = null;
        $agrupacion = null;
        foreach ($lector->filas($this->ruta('CNED14_A_estr.xls')) as $fila) {
            $codigo = trim($fila[0] ?? '');
            $texto = trim(preg_replace('/\s+/', ' ', $fila[1] ?? ''));

            if (preg_match('/^[A-C]$/', $codigo)) {
                $etapa = $codigo;
            } elseif (preg_match('/^\d$/', $codigo)) {
                $agrupacion = $codigo;
            } elseif (preg_match('/^\d{2}$/', $codigo)) {
                $filas[] = [
                    'clave' => $codigo,
                    'etiqueta' => $texto,
                    'orden' => count($filas) + 1,
                    'atributos' => ['agrupacion' => $agrupacion, 'etapa' => $etapa],
                    'equivalencias' => ['cine2011' => $cine[$codigo] ?? throw new RuntimeException("CNED {$codigo} sin correspondencia CINE-2011.")],
                    'fuente' => $this->fuente('CNED14_A_estr.xls'),
                ];
            }
        }

        return $filas;
    }

    /**
     * Situación de actividad autopercibida de la EPA (decisión del 26 nov 2026, en lugar de AOI).
     *
     * Une las dos variables del diseño de registro: SIDAC (activos: trabajando, buscando
     * empleo) y SIDI (inactivos), sin el código 00 de SIDI («no sabe / no refiere»), que
     * aquí cubre «No desea contestar». Las claves llevan el nombre de la variable como
     * prefijo para que no choquen (SIDAC-1, SIDI-01). `grupo`: SIDAC 1 → ocupado,
     * SIDAC 2 → parado, SIDI → inactivo, según la definición de cada variable.
     *
     * @return list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}>
     *
     * @throws RuntimeException Si no aparecen las tablas TSIDAC o TSID.
     */
    public function situacionesLaborales(): array
    {
        $filasHoja = (new LectorXlsx)->filas($this->ruta('dr_EPA_2026.xlsx'), 'Tablas1');
        $grupoSidac = ['1' => 'ocupado', '2' => 'parado'];
        $filas = [];

        foreach ($this->tablaEpa($filasHoja, 'TSIDAC') as $codigo => $texto) {
            $filas[] = $this->situacion("SIDAC-{$codigo}", $texto, count($filas) + 1, $grupoSidac[$codigo] ?? 'ocupado', 'SIDAC', $codigo);
        }
        foreach ($this->tablaEpa($filasHoja, 'TSID') as $codigo => $texto) {
            if ($codigo !== '00') {
                $filas[] = $this->situacion("SIDI-{$codigo}", $texto, count($filas) + 1, 'inactivo', 'SIDI', $codigo);
            }
        }

        return $filas;
    }

    /**
     * Idiomas vivos de ISO 639 con nombre en español de CLDR.
     *
     * Del fichero de SIL se toman las lenguas individuales y macrolenguas vivas que tienen
     * código ISO 639-1 o 639-2. Clave: 639-1 si existe; si no, 639-2 (terminológico).
     * Nombre: CLDR `es` (con mayúscula inicial); si CLDR no lo tiene, el nombre de referencia
     * de SIL, en inglés, con `atributos.nombre_es = false`.
     *
     * @return list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}>
     */
    public function idiomas(): array
    {
        $cldr = json_decode((string) file_get_contents($this->ruta('cldr_es_languages.json')), true);
        $nombres = $cldr['main']['es']['localeDisplayNames']['languages'] ?? [];

        $lineas = preg_split('/\R/', trim((string) file_get_contents($this->ruta('iso-639-3.tab'))));
        $cabecera = explode("\t", array_shift($lineas));
        $filas = [];

        foreach ($lineas as $linea) {
            $l = array_combine($cabecera, array_pad(explode("\t", $linea), count($cabecera), ''));
            $parte2 = $l['Part2t'] ?: $l['Part2b'];
            // I = individual, M = macrolengua; L = viva
            if (! in_array($l['Scope'], ['I', 'M'], true) || $l['Language_Type'] !== 'L' || ($l['Part1'] === '' && $parte2 === '')) {
                continue;
            }

            $clave = $l['Part1'] ?: $parte2;
            $nombre = $nombres[$clave] ?? $nombres[$l['Id']] ?? null;

            $filas[] = [
                'clave' => $clave,
                'etiqueta' => $nombre !== null ? mb_strtoupper(mb_substr($nombre, 0, 1)).mb_substr($nombre, 1) : $l['Ref_Name'],
                'orden' => 0,
                'atributos' => ['macrolengua' => $l['Scope'] === 'M', 'nombre_es' => $nombre !== null],
                'equivalencias' => array_filter([
                    'iso639_1' => $l['Part1'],
                    'iso639_2' => $parte2,
                    'iso639_2b' => $l['Part2b'] !== $parte2 ? $l['Part2b'] : '',
                    'iso639_3' => $l['Id'],
                ], fn (string $v) => $v !== ''),
                'fuente' => $this->fuente('iso-639-3.tab'),
            ];
        }

        return $this->ordenarPorEtiqueta($filas);
    }

    /**
     * Escribe un CSV normalizado con el formato de `CargaCatalogosOficiales`.
     *
     * @param string $destino Ruta del CSV.
     * @param list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}> $filas
     *
     * @return void
     */
    public function escribir(string $destino, array $filas): void
    {
        $json = fn (array $valor): string => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $manejador = fopen($destino, 'w');
        fputcsv($manejador, CargaCatalogosOficiales::CABECERA, ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($manejador, [
                $fila['clave'], $fila['etiqueta'], $fila['orden'],
                $json($fila['atributos']), $json($fila['equivalencias']), $fila['fuente'],
            ], ';', '"', '');
        }
        fclose($manejador);
    }

    /**
     * Filas de la tabla en español de M49, por nombre de columna (cabecera en inglés).
     *
     * @return list<array<string, string>>
     *
     * @throws RuntimeException Si la página no contiene la tabla en español.
     */
    private function tablaM49(): array
    {
        $dom = new DOMDocument;
        // La página no es XHTML estricta: se silencian los avisos del parser
        @$dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($this->ruta('m49_overview.html')));
        $tabla = (new DOMXPath($dom))->query('//table[@id="downloadTableES"]')->item(0);
        if (! $tabla instanceof DOMElement) {
            throw new RuntimeException('m49_overview.html no contiene la tabla downloadTableES.');
        }

        $cabecera = [];
        $filas = [];
        foreach ($tabla->getElementsByTagName('tr') as $tr) {
            $celdas = [];
            foreach ($tr->childNodes as $nodo) {
                if (in_array($nodo->nodeName, ['td', 'th'], true)) {
                    $celdas[] = trim($nodo->textContent);
                }
            }
            if ($cabecera === []) {
                $cabecera = $celdas;

                continue;
            }
            $filas[] = array_combine($cabecera, array_pad(array_slice($celdas, 0, count($cabecera)), count($cabecera), ''));
        }

        return $filas;
    }

    /**
     * Códigos ISO alfa-2 de los Estados miembros según el glosario de Eurostat.
     *
     * @return list<string>
     *
     * @throws RuntimeException Si no se encuentra el bloque de la UE.
     */
    private function estadosMiembrosUe(): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($this->ruta('eurostat_glossary_country_codes.html')));
        $texto = preg_replace('/\s+/', ' ', $dom->textContent);

        // Tabla de la UE: «European Union (EU)» seguido directamente de «País (XX)», hasta el
        // bloque de la AELC. La primera mención de «European Union (EU)» es la introducción.
        if (! preg_match('/European Union \(EU\) ((?:[^()]+ \([A-Z]{2}\) )+)European Free Trade Association/', $texto, $bloque)) {
            throw new RuntimeException('El glosario de Eurostat no tiene el bloque de la UE.');
        }
        preg_match_all('/\(([A-Z]{2})\)/', $bloque[1], $codigos);

        if ($codigos[1] === []) {
            throw new RuntimeException('El bloque de la UE del glosario de Eurostat no tiene códigos.');
        }

        return array_map(fn (string $c) => self::EUROSTAT_A_ISO2[$c] ?? $c, $codigos[1]);
    }

    /**
     * Códigos y descripciones de una tabla de valores del diseño de registro de la EPA.
     *
     * @param list<list<string>> $filas Filas de la hoja.
     * @param string $tabla Nombre de la tabla (TSIDAC, TSID…).
     *
     * @return array<string, string> Código → descripción.
     *
     * @throws RuntimeException Si la tabla no aparece.
     */
    private function tablaEpa(array $filas, string $tabla): array
    {
        $valores = [];
        $dentro = false;
        foreach ($filas as $fila) {
            $primera = trim($fila[0] ?? '');
            if ($primera === $tabla) {
                $dentro = true;

                continue;
            }
            if (! $dentro || str_starts_with($primera, 'Código')) {
                continue;
            }
            if (! preg_match('/^\d+$/', $primera)) {
                break;
            }
            $valores[$primera] = trim(preg_replace('/\s+/', ' ', $fila[1] ?? ''));
        }

        if ($valores === []) {
            throw new RuntimeException("dr_EPA_2026.xlsx no contiene la tabla {$tabla}.");
        }

        return $valores;
    }

    /**
     * Fila de situación laboral.
     *
     * @param string $clave Clave con prefijo de variable.
     * @param string $etiqueta Descripción oficial.
     * @param int $orden Posición.
     * @param string $grupo ocupado, parado o inactivo.
     * @param string $variable SIDAC o SIDI.
     * @param string $codigo Código original en la variable.
     *
     * @return array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}
     */
    private function situacion(string $clave, string $etiqueta, int $orden, string $grupo, string $variable, string $codigo): array
    {
        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'orden' => $orden,
            'atributos' => ['grupo' => $grupo],
            'equivalencias' => ['epa_variable' => $variable, 'epa_codigo' => $codigo],
            'fuente' => $this->fuente('dr_EPA_2026.xlsx'),
        ];
    }

    /**
     * Ordena alfabéticamente en español y numera `orden` (de uno en uno).
     *
     * @param list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}> $filas
     *
     * @return list<array{clave: string, etiqueta: string, orden: int, atributos: array<string, mixed>, equivalencias: array<string, mixed>, fuente: string}>
     */
    private function ordenarPorEtiqueta(array $filas): array
    {
        $collator = new Collator('es_ES');
        usort($filas, fn (array $a, array $b): int => $collator->compare($a['etiqueta'], $b['etiqueta']) ?: strcmp($a['clave'], $b['clave']));

        foreach ($filas as $i => &$fila) {
            $fila['orden'] = $i + 1;
        }

        return $filas;
    }

    /**
     * Texto de la columna `fuente` de un fichero, según el manifiesto.
     *
     * @param string $fichero Fichero original.
     *
     * @return string
     *
     * @throws RuntimeException Si el fichero no está en el manifiesto.
     */
    private function fuente(string $fichero): string
    {
        return $this->manifiesto[$fichero]['fuente'] ?? throw new RuntimeException("{$fichero} no está en fuentes.json.");
    }

    /**
     * Ruta de un fichero original, comprobando que existe.
     *
     * @param string $fichero Nombre del fichero.
     *
     * @return string
     *
     * @throws RuntimeException Si no existe.
     */
    private function ruta(string $fichero): string
    {
        $ruta = rtrim($this->fuentes, '/').'/'.$fichero;
        if (! is_file($ruta)) {
            throw new RuntimeException("Falta el fichero original {$ruta}.");
        }

        return $ruta;
    }
}
