<?php

namespace Modules\Documentos\Services\Ingesta;

use Modules\Documentos\Exceptions\IngestaRechazadaException;

/**
 * Detecta el formato de un fichero por su contenido (magic bytes y estructura del
 * paquete en ODT/DOCX). La extensión y el MIME que declare el navegador se ignoran.
 *
 * Solo admite PDF, JPEG, PNG, HEIC, ODT y DOCX. Todo lo demás se rechaza, en
 * particular contenedores (zip), ejecutables, scripts y formatos con macros.
 */
class DetectorFormato
{
    public const PDF = 'application/pdf';

    public const JPEG = 'image/jpeg';

    public const PNG = 'image/png';

    public const HEIC = 'image/heic';

    public const ODT = 'application/vnd.oasis.opendocument.text';

    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** Tipo de la parte principal de un DOCX sin macros. */
    private const DOCX_PRINCIPAL = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';

    /**
     * Detecta y valida el formato.
     *
     * @param string $ruta Fichero en la zona temporal.
     *
     * @throws IngestaRechazadaException formato_no_admitido o macros_no_admitidas
     *
     * @return string MIME normalizado (una de las constantes de esta clase).
     */
    public function detectar(string $ruta): string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);

        return match ($mime) {
            self::PDF, self::JPEG, self::PNG => $mime,
            'image/heic', 'image/heif' => self::HEIC,
            // finfo reconoce ODT y DOCX por la primera entrada del zip, que puede no estar
            // en su sitio; por eso cualquier zip se examina por dentro antes de decidir.
            self::ODT, self::DOCX, 'application/zip', 'application/octet-stream' => $this->detectarPaquete($ruta),
            default => throw new IngestaRechazadaException('formato_no_admitido'),
        };
    }

    /**
     * Identifica un paquete zip como ODT o DOCX y comprueba que no lleva macros.
     *
     * @param string $ruta Fichero en la zona temporal.
     *
     * @throws IngestaRechazadaException
     *
     * @return string
     */
    private function detectarPaquete(string $ruta): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true) {
            throw new IngestaRechazadaException('formato_no_admitido');
        }

        try {
            $entradas = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entradas[] = (string) $zip->getNameIndex($i);
            }

            if ($zip->getFromName('mimetype') === self::ODT) {
                // Las macros de un ODT viven en Basic/ (StarBasic) o Scripts/.
                $this->rechazarSiHayMacros($entradas, '#^(Basic|Scripts)/#i');

                return self::ODT;
            }

            $tipos = $zip->getFromName('[Content_Types].xml');
            if (is_string($tipos)) {
                // Cualquier paquete OOXML con proyecto VBA (.docm, .xlsm…): tipo «macroEnabled» o vbaProject.bin.
                if (stripos($tipos, 'macroEnabled') !== false || stripos($tipos, 'vbaProject') !== false) {
                    throw new IngestaRechazadaException('macros_no_admitidas');
                }
                $this->rechazarSiHayMacros($entradas, '#vbaProject|vbaData#i');

                if (str_contains($tipos, self::DOCX_PRINCIPAL)) {
                    return self::DOCX;
                }
            }
        } finally {
            $zip->close();
        }

        throw new IngestaRechazadaException('formato_no_admitido');
    }

    /**
     * Rechaza el paquete si alguna entrada corresponde a macros.
     *
     * @param list<string> $entradas Nombres de las entradas del zip.
     * @param string $patron Expresión regular de las entradas de macros.
     *
     * @throws IngestaRechazadaException
     *
     * @return void
     */
    private function rechazarSiHayMacros(array $entradas, string $patron): void
    {
        foreach ($entradas as $entrada) {
            if (preg_match($patron, $entrada)) {
                throw new IngestaRechazadaException('macros_no_admitidas');
            }
        }
    }
}
