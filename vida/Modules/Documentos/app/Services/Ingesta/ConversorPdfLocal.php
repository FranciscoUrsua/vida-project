<?php

namespace Modules\Documentos\Services\Ingesta;

use Illuminate\Support\Facades\Process;
use Modules\Documentos\Contracts\ConversorPdf;
use Modules\Documentos\Exceptions\IngestaRechazadaException;

/**
 * Conversión a PDF con herramientas locales: Imagick para imágenes (una página por
 * imagen) y LibreOffice headless para ODT y DOCX.
 *
 * Todo se escribe dentro del directorio de trabajo de la ingesta, que la tubería
 * borra al terminar. El PDF resultante pasa después por el saneado.
 */
class ConversorPdfLocal implements ConversorPdf
{
    /** Lado mayor de un A4 en pulgadas: la imagen se ajusta para caber en él. */
    private const A4_LADO_MAYOR_PULGADAS = 11.69;

    /**
     * {@inheritDoc}
     */
    public function convertir(string $ruta, string $mime, string $directorio): string
    {
        return match ($mime) {
            DetectorFormato::JPEG, DetectorFormato::PNG, DetectorFormato::HEIC => $this->desdeImagen($ruta, $mime, $directorio),
            DetectorFormato::ODT, DetectorFormato::DOCX => $this->desdeOfimatica($ruta, $directorio),
            default => throw new IngestaRechazadaException('conversion_fallida'),
        };
    }

    /**
     * Convierte una imagen en un PDF de una página del tamaño de un A4 como máximo.
     *
     * Se eliminan los metadatos (EXIF, que puede incluir la ubicación GPS) y se
     * respeta la orientación de la cámara.
     *
     * @param string $ruta Imagen de entrada.
     * @param string $mime MIME detectado, que fija el decodificador.
     * @param string $directorio Directorio de trabajo.
     *
     * @throws IngestaRechazadaException
     *
     * @return string
     */
    private function desdeImagen(string $ruta, string $mime, string $directorio): string
    {
        $salida = $directorio.'/convertido.pdf';

        try {
            // Sin límite de tiempo de ImageMagick: cuenta desde que arranca el proceso, no por
            // conversión, y en un worker de PHP-FPM de larga vida haría fallar todas. La
            // política de ImageMagick limita memoria, área y tamaño de imagen.
            $imagen = new \Imagick;
            // El decodificador lo fija el tipo detectado por contenido, no lo adivina ImageMagick.
            $imagen->readImage(substr($mime, strlen('image/')).':'.$ruta);
            // Un fichero de imagen es una sola página: si trae varios fotogramas, el primero.
            $imagen->setIteratorIndex(0);
            $pagina = $imagen->getImage();
            $imagen->clear();

            $pagina->autoOrient();
            $pagina->stripImage();
            $resolucion = max($pagina->getImageWidth(), $pagina->getImageHeight()) / self::A4_LADO_MAYOR_PULGADAS;
            $pagina->setImageUnits(\Imagick::RESOLUTION_PIXELSPERINCH);
            $pagina->setImageResolution($resolucion, $resolucion);
            $pagina->setImageFormat('pdf');
            $pagina->writeImage($salida);
            $pagina->clear();
        } catch (\ImagickException $e) {
            throw new IngestaRechazadaException('conversion_fallida', $e);
        }

        return $salida;
    }

    /**
     * Convierte un ODT o DOCX con LibreOffice headless.
     *
     * Cada conversión usa un perfil de LibreOffice propio dentro del directorio de
     * trabajo: evita bloqueos entre conversiones simultáneas y no depende del HOME
     * del usuario de PHP-FPM.
     *
     * @param string $ruta Documento de entrada.
     * @param string $directorio Directorio de trabajo.
     *
     * @throws IngestaRechazadaException
     *
     * @return string
     */
    private function desdeOfimatica(string $ruta, string $directorio): string
    {
        $salida = $directorio.'/convertido';

        try {
            $resultado = Process::timeout((int) config('documentos.ingesta.timeout_conversion_segundos'))
                ->env(['HOME' => $directorio])
                ->run([
                    (string) config('documentos.binarios.soffice'),
                    '-env:UserInstallation=file://'.$directorio.'/perfil-libreoffice',
                    '--headless',
                    '--norestore',
                    '--convert-to', 'pdf:writer_pdf_Export',
                    '--outdir', $salida,
                    $ruta,
                ]);
        } catch (\Throwable $e) {
            throw new IngestaRechazadaException('conversion_fallida', $e);
        }

        $pdf = $salida.'/'.pathinfo($ruta, PATHINFO_FILENAME).'.pdf';

        if ($resultado->failed() || ! is_file($pdf)) {
            throw new IngestaRechazadaException('conversion_fallida');
        }

        return $pdf;
    }
}
