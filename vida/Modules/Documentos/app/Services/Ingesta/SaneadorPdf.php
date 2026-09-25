<?php

namespace Modules\Documentos\Services\Ingesta;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Modules\Documentos\Exceptions\IngestaRechazadaException;

/**
 * Saneado y normalización de PDF con qpdf y Ghostscript.
 *
 * Reescribe el PDF a PDF/A-2b eliminando JavaScript, ficheros incrustados,
 * anotaciones y acciones (incluidas las de lanzamiento de programas), y después
 * comprueba que el resultado no conserva ninguno de esos objetos.
 */
class SaneadorPdf
{
    /** Nombres de objetos PDF que nunca pueden quedar en un documento custodiado. */
    private const OBJETOS_PROHIBIDOS = '#/(JavaScript|JS|EmbeddedFiles?|Launch)(?=[\s/<>\[\]()%]|$)#';

    /** Parámetros comunes de reescritura con Ghostscript. */
    private const GS_PDFA = [
        '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
        '-sDEVICE=pdfwrite',
        '-dPDFA=2', '-dPDFACompatibilityPolicy=1', '-sColorConversionStrategy=RGB',
        // Sin estos tres, Ghostscript conserva la OpenAction con JavaScript y los adjuntos.
        '-dPreserveEmbeddedFiles=false', '-dPreserveDocView=false', '-dPreserveAnnots=false',
    ];

    /**
     * Rechaza el PDF si hace falta una contraseña para abrirlo.
     *
     * Un PDF con solo contraseña de propietario (restricciones de impresión o copia)
     * se puede abrir y se admite: el saneado quita esas restricciones.
     *
     * @param string $ruta PDF.
     *
     * @throws IngestaRechazadaException pdf_protegido o pdf_no_normalizable
     *
     * @return void
     */
    public function rechazarSiProtegido(string $ruta): void
    {
        // qpdf --requires-password: 0 = necesita contraseña, 2 = sin cifrar, 3 = cifrado sin contraseña de usuario.
        $resultado = $this->ejecutar([(string) config('documentos.binarios.qpdf'), '--requires-password', $ruta]);

        match ($resultado->exitCode()) {
            0 => throw new IngestaRechazadaException('pdf_protegido'),
            2, 3 => null,
            default => throw new IngestaRechazadaException('pdf_no_normalizable'),
        };
    }

    /**
     * Reescribe el PDF a PDF/A-2b sin contenido activo.
     *
     * @param string $ruta PDF de entrada.
     * @param string $salida Ruta del PDF saneado.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable
     *
     * @return void
     */
    public function sanear(string $ruta, string $salida): void
    {
        $this->reescribir($ruta, $salida, []);
    }

    /**
     * Segunda reescritura con reducción de imágenes a 150 ppp, para ajustar el tamaño.
     *
     * @param string $ruta PDF ya saneado.
     * @param string $salida Ruta del PDF recomprimido.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable
     *
     * @return void
     */
    public function recomprimir(string $ruta, string $salida): void
    {
        $this->reescribir($ruta, $salida, ['-dPDFSETTINGS=/ebook']);
    }

    /**
     * Número de páginas del PDF.
     *
     * @param string $ruta PDF.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable si no se puede interpretar
     *
     * @return int
     */
    public function contarPaginas(string $ruta): int
    {
        $resultado = $this->ejecutar([(string) config('documentos.binarios.pdfinfo'), $ruta]);

        if ($resultado->failed() || ! preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $coincidencia)) {
            throw new IngestaRechazadaException('pdf_no_normalizable');
        }

        return (int) $coincidencia[1];
    }

    /**
     * Comprueba que el PDF no contiene JavaScript, adjuntos ni acciones de lanzamiento.
     *
     * Se examina la forma expandida (qpdf --qdf, sin flujos de objetos ni compresión)
     * para que ningún objeto quede oculto dentro de un flujo comprimido.
     *
     * @param string $ruta PDF.
     * @param string $directorio Directorio de trabajo donde dejar la forma expandida.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable si queda algún objeto prohibido
     *
     * @return void
     */
    public function verificarSinContenidoActivo(string $ruta, string $directorio): void
    {
        $expandido = $directorio.'/expandido.pdf';
        $resultado = $this->ejecutar([
            (string) config('documentos.binarios.qpdf'),
            '--qdf', '--object-streams=disable', '--decode-level=generalized',
            $ruta, $expandido,
        ]);

        // qpdf devuelve 3 cuando termina con avisos; el fichero es válido igualmente.
        if (! in_array($resultado->exitCode(), [0, 3], true) || ! is_file($expandido)) {
            throw new IngestaRechazadaException('pdf_no_normalizable');
        }

        $contenido = (string) file_get_contents($expandido);
        @unlink($expandido);

        if (preg_match(self::OBJETOS_PROHIBIDOS, $contenido)) {
            throw new IngestaRechazadaException('pdf_no_normalizable');
        }
    }

    /**
     * Reescritura con Ghostscript a PDF/A-2b.
     *
     * @param string $ruta PDF de entrada.
     * @param string $salida PDF de salida.
     * @param list<string> $extra Parámetros adicionales.
     *
     * @throws IngestaRechazadaException
     *
     * @return void
     */
    private function reescribir(string $ruta, string $salida, array $extra): void
    {
        $resultado = $this->ejecutar([
            (string) config('documentos.binarios.gs'),
            ...self::GS_PDFA,
            ...$extra,
            '-o', $salida,
            $ruta,
        ]);

        // Ghostscript puede terminar con código 0 sin generar páginas (p. ej. si no puede abrir el PDF).
        if ($resultado->failed() || ! is_file($salida) || filesize($salida) === 0) {
            throw new IngestaRechazadaException('pdf_no_normalizable');
        }
    }

    /**
     * Ejecuta un binario con el timeout de saneado.
     *
     * @param list<string> $comando Comando y argumentos.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable si se agota el tiempo
     *
     * @return ProcessResult
     */
    private function ejecutar(array $comando): ProcessResult
    {
        try {
            return Process::timeout((int) config('documentos.ingesta.timeout_saneado_segundos'))->run($comando);
        } catch (\Throwable $e) {
            throw new IngestaRechazadaException('pdf_no_normalizable', $e);
        }
    }
}
