<?php

namespace Modules\Documentos\Services\Firma;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;
use Modules\Documentos\Exceptions\FirmaRechazadaException;
use Modules\Documentos\Models\Informe;
use Modules\Documentos\Services\ServicioGeneracionPDF;

/**
 * Validación mínima del PDF firmado de un informe (fase 5, F5-2).
 *
 * Comprueba, con poppler-utils, que el PDF:
 * 1. se puede abrir (`pdfinfo`);
 * 2. tiene al menos una firma íntegra (`pdfsig`: «Signature is Valid»);
 * 3. corresponde al informe: mismo número de páginas que el PDF para firmar generado
 *    ahora, el identificador del informe en el pie de todas las páginas y sin la
 *    marca de borrador (`pdftotext`).
 *
 * No valida la cadena de confianza del certificado (CRL/OCSP) ni extrae el número
 * de colegiado: fuera de alcance de la fase 5 (BACKLOG). Devuelve el titular del
 * certificado que informa `pdfsig`.
 *
 * Un fallo de un binario (código inesperado, salida ilegible, timeout) se rechaza con
 * `fallo_tecnico` y la causa (`FalloHerramientaExterna`) como excepción previa, para el log.
 */
class ValidadorFirmaPdf
{
    /**
     * Crea el validador.
     *
     * @param ServicioGeneracionPDF $generacionPdf Para regenerar el PDF que se firmó.
     */
    public function __construct(private ServicioGeneracionPDF $generacionPdf) {}

    /**
     * Valida el PDF firmado de un informe y devuelve el titular del certificado.
     *
     * @param string $pdf Contenido binario del PDF subido.
     * @param Informe $informe Informe al que debe corresponder.
     *
     * @return string Titular (nombre común) del certificado de la primera firma íntegra.
     *
     * @throws FirmaRechazadaException si no supera alguna comprobación
     */
    public function validar(string $pdf, Informe $informe): string
    {
        $directorio = storage_path('app/tmp/firma/'.Str::uuid()->toString());
        File::ensureDirectoryExists($directorio, 0700);
        $ruta = $directorio.'/firmado.pdf';
        $rutaEsperado = $directorio.'/esperado.pdf';

        try {
            File::put($ruta, $pdf);
            chmod($ruta, 0600);

            $paginas = $this->contarPaginas($ruta, 'pdf_ilegible');
            $titular = $this->titularFirmaIntegra($ruta);

            File::put($rutaEsperado, $this->generacionPdf->generarParaFirmar($informe));
            if ($paginas !== $this->contarPaginas($rutaEsperado, 'fallo_tecnico')) {
                throw new FirmaRechazadaException('no_corresponde');
            }

            $this->comprobarContenido($ruta, $paginas, $informe);

            return $titular;
        } finally {
            File::deleteDirectory($directorio);
        }
    }

    /**
     * Titular de la primera firma íntegra del PDF.
     *
     * `pdfsig` sale con 0 si todas las firmas son válidas, con 1 si alguna no lo es o
     * no puede abrir el fichero, y con 2 si no hay firmas. Por eso se interpreta la
     * salida y no solo el código. Se ejecuta con `LC_ALL=C` para leerla en inglés.
     *
     * @param string $ruta PDF subido.
     *
     * @return string
     *
     * @throws FirmaRechazadaException sin_firma, firma_no_integra o fallo_tecnico
     */
    private function titularFirmaIntegra(string $ruta): string
    {
        $comando = [(string) config('documentos.binarios.pdfsig'), $ruta];
        $resultado = $this->ejecutar($comando);
        $salida = $resultado->output();

        if (str_contains($salida, 'does not contain any signatures')) {
            throw new FirmaRechazadaException('sin_firma');
        }

        if (! str_contains($salida, 'Digital Signature Info of')) {
            throw new FirmaRechazadaException(
                'fallo_tecnico',
                FalloHerramientaExterna::desde($comando, $resultado, 'pdfsig no ha devuelto información de firmas'),
            );
        }

        // Un bloque por firma: «Signature #N:» seguido de sus líneas «  - Clave: valor».
        $bloques = preg_split('/^Signature #\d+:\s*$/m', $salida) ?: [];
        array_shift($bloques);

        foreach ($bloques as $bloque) {
            if (preg_match('/Signature Validation:\s*Signature is Valid\./', $bloque)) {
                preg_match('/Signer Certificate Common Name:\s*(.+)$/m', $bloque, $cn);

                return trim($cn[1] ?? '') !== '' ? trim($cn[1]) : 'Titular no identificado';
            }
        }

        throw new FirmaRechazadaException('firma_no_integra');
    }

    /**
     * Comprueba que todas las páginas llevan el identificador del informe y ninguna
     * la marca de borrador.
     *
     * @param string $ruta PDF subido.
     * @param int $paginas Número de páginas.
     * @param Informe $informe Informe esperado.
     *
     * @return void
     *
     * @throws FirmaRechazadaException no_corresponde, es_borrador o fallo_tecnico
     */
    private function comprobarContenido(string $ruta, int $paginas, Informe $informe): void
    {
        $comando = [(string) config('documentos.binarios.pdftotext'), '-enc', 'UTF-8', $ruta, '-'];
        $resultado = $this->ejecutar($comando);

        if ($resultado->failed()) {
            throw new FirmaRechazadaException(
                'fallo_tecnico',
                FalloHerramientaExterna::desde($comando, $resultado, 'pdftotext no ha podido extraer el texto'),
            );
        }

        // pdftotext separa las páginas con un salto de página (\f).
        $textoPaginas = array_slice(explode("\f", $resultado->output()), 0, $paginas);

        if (str_contains($resultado->output(), ServicioGeneracionPDF::MARCA_BORRADOR)) {
            throw new FirmaRechazadaException('es_borrador');
        }

        foreach ($textoPaginas as $texto) {
            if (! str_contains($texto, $informe->identificador())) {
                throw new FirmaRechazadaException('no_corresponde');
            }
        }
    }

    /**
     * Número de páginas de un PDF con `pdfinfo`.
     *
     * @param string $ruta PDF.
     * @param string $motivo Código de rechazo si no se puede leer.
     *
     * @return int
     *
     * @throws FirmaRechazadaException con el motivo indicado
     */
    private function contarPaginas(string $ruta, string $motivo): int
    {
        $comando = [(string) config('documentos.binarios.pdfinfo'), $ruta];
        $resultado = $this->ejecutar($comando);

        if ($resultado->failed() || ! preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $coincidencia)) {
            throw new FirmaRechazadaException(
                $motivo,
                FalloHerramientaExterna::desde($comando, $resultado, 'pdfinfo no ha devuelto el número de páginas'),
            );
        }

        return (int) $coincidencia[1];
    }

    /**
     * Ejecuta un binario con salida en inglés y el timeout de la firma.
     *
     * @param list<string> $comando Comando y argumentos.
     *
     * @return ProcessResult
     *
     * @throws FirmaRechazadaException fallo_tecnico si el proceso no termina
     */
    private function ejecutar(array $comando): ProcessResult
    {
        try {
            return Process::env(['LC_ALL' => 'C'])
                ->timeout((int) config('documentos.firma.timeout_segundos', 30))
                ->run($comando);
        } catch (\Throwable $e) {
            throw new FirmaRechazadaException(
                'fallo_tecnico',
                new FalloHerramientaExterna($comando, null, '', 'El proceso no ha terminado: '.$e->getMessage(), $e),
            );
        }
    }
}
