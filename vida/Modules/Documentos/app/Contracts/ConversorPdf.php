<?php

namespace Modules\Documentos\Contracts;

use Modules\Documentos\Exceptions\IngestaRechazadaException;

/**
 * Conversión a PDF de los formatos admitidos que no lo son (imágenes y ofimática).
 */
interface ConversorPdf
{
    /**
     * Convierte un fichero a PDF dentro del directorio de trabajo de la ingesta.
     *
     * @param string $ruta Fichero de entrada, ya validado y analizado por el antivirus.
     * @param string $mime MIME detectado por contenido.
     * @param string $directorio Directorio de trabajo de esta ingesta (zona temporal).
     *
     * @throws IngestaRechazadaException con código conversion_fallida si no se puede convertir
     *
     * @return string Ruta del PDF resultante, dentro de $directorio.
     */
    public function convertir(string $ruta, string $mime, string $directorio): string;
}
