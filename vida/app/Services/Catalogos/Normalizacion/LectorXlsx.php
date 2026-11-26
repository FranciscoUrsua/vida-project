<?php

namespace App\Services\Catalogos\Normalizacion;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lector mínimo de libros OOXML (`.xlsx`) para normalizar los ficheros oficiales del INE.
 *
 * Solo devuelve el texto de las celdas de una hoja, fila a fila, respetando la columna de
 * cada celda (las vacías quedan como cadena vacía). No evalúa fórmulas ni formatos: los
 * ficheros de clasificaciones solo tienen texto y números. Evita añadir una dependencia
 * (PhpSpreadsheet) para un comando que no se ejecuta en producción.
 */
class LectorXlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Filas de una hoja como listas de textos.
     *
     * @param string $ruta Ruta del libro (los `.xls` del INE son OOXML aunque su extensión diga otra cosa).
     * @param string|null $hoja Nombre de la hoja; la primera si es nulo.
     *
     * @return list<list<string>>
     *
     * @throws RuntimeException Si el fichero no es un libro OOXML o no tiene esa hoja.
     */
    public function filas(string $ruta, ?string $hoja = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException("{$ruta} no es un libro OOXML legible.");
        }

        try {
            $compartidas = $this->cadenasCompartidas($zip);
            $xml = $this->xml($zip, $this->rutaHoja($zip, $hoja, $ruta));

            $filas = [];
            foreach ($xml->children(self::NS)->sheetData->children(self::NS)->row as $fila) {
                $celdas = [];
                foreach ($fila->children(self::NS)->c as $celda) {
                    $celdas[$this->columna((string) $celda->attributes()['r'])] = $this->texto($celda, $compartidas);
                }
                if ($celdas === []) {
                    continue;
                }

                $completa = array_fill(0, max(array_keys($celdas)) + 1, '');
                $filas[] = array_replace($completa, $celdas);
            }

            return $filas;
        } finally {
            $zip->close();
        }
    }

    /**
     * Tabla de cadenas compartidas del libro.
     *
     * @param ZipArchive $zip Libro abierto.
     *
     * @return list<string>
     */
    private function cadenasCompartidas(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $cadenas = [];
        foreach ($this->xml($zip, 'xl/sharedStrings.xml')->children(self::NS)->si as $si) {
            // Una cadena puede venir en varios tramos con formato (<r><t>…</t></r>)
            $cadenas[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]') ?: []));
        }

        return $cadenas;
    }

    /**
     * Ruta interna de la hoja pedida, resuelta por las relaciones del libro.
     *
     * @param ZipArchive $zip Libro abierto.
     * @param string|null $hoja Nombre de la hoja o nulo para la primera.
     * @param string $ruta Ruta del libro, para el mensaje de error.
     *
     * @return string
     *
     * @throws RuntimeException Si la hoja no existe.
     */
    private function rutaHoja(ZipArchive $zip, ?string $hoja, string $ruta): string
    {
        $libro = $this->xml($zip, 'xl/workbook.xml');
        $relaciones = [];
        foreach ($this->xml($zip, 'xl/_rels/workbook.xml.rels')->children() as $rel) {
            $relaciones[(string) $rel['Id']] = ltrim(str_replace('/xl/', '', (string) $rel['Target']), '/');
        }

        foreach ($libro->children(self::NS)->sheets->children(self::NS)->sheet as $sheet) {
            if ($hoja === null || (string) $sheet->attributes()['name'] === $hoja) {
                $id = (string) $sheet->attributes(self::NS_REL)['id'];

                return 'xl/'.$relaciones[$id];
            }
        }

        throw new RuntimeException("{$ruta} no tiene la hoja {$hoja}.");
    }

    /**
     * Texto de una celda (cadena compartida, cadena en línea o número tal cual).
     *
     * @param SimpleXMLElement $celda Elemento `<c>`.
     * @param list<string> $compartidas Cadenas compartidas.
     *
     * @return string
     */
    private function texto(SimpleXMLElement $celda, array $compartidas): string
    {
        $hijos = $celda->children(self::NS);

        return match ((string) $celda->attributes()['t']) {
            's' => $compartidas[(int) $hijos->v] ?? '',
            'inlineStr' => implode('', array_map('strval', $celda->xpath('.//*[local-name()="t"]') ?: [])),
            default => (string) $hijos->v,
        };
    }

    /**
     * Índice de columna (0 = A) a partir de la referencia de la celda («C12» → 2).
     *
     * @param string $referencia Referencia de la celda.
     *
     * @return int
     */
    private function columna(string $referencia): int
    {
        $letras = preg_replace('/\d/', '', $referencia);
        $indice = 0;
        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    /**
     * Carga un XML del libro.
     *
     * @param ZipArchive $zip Libro abierto.
     * @param string $ruta Ruta interna.
     *
     * @return SimpleXMLElement
     *
     * @throws RuntimeException Si no existe o no es XML.
     */
    private function xml(ZipArchive $zip, string $ruta): SimpleXMLElement
    {
        $contenido = $zip->getFromName($ruta);
        $xml = $contenido === false ? false : simplexml_load_string($contenido);
        if ($xml === false) {
            throw new RuntimeException("El libro no contiene {$ruta} legible.");
        }

        return $xml;
    }
}
