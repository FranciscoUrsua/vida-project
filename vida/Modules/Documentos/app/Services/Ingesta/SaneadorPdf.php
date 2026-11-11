<?php

namespace Modules\Documentos\Services\Ingesta;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Modules\Documentos\Exceptions\IngestaRechazadaException;
use Modules\Documentos\Exceptions\FalloHerramientaExterna;

/**
 * Saneado y normalización de PDF con qpdf y Ghostscript.
 *
 * Reescribe el PDF a PDF/A-2b eliminando JavaScript, ficheros incrustados,
 * anotaciones y acciones (incluidas las de lanzamiento de programas), y después
 * comprueba que el resultado no conserva ninguno de esos objetos.
 *
 * Todo rechazo por un fallo de proceso lleva como excepción previa una
 * FalloHerramientaExterna con el comando, el código de salida y la salida
 * de error, para poder diagnosticarlo en el registro (p. ej. un perfil de AppArmor
 * que impide a qpdf o gs leer la zona temporal).
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
     * El código 2 de qpdf significa «sin cifrar» solo si la salida de error está vacía:
     * qpdf también devuelve 2 cuando no puede abrir o interpretar el fichero (permiso
     * denegado, PDF dañado), y aceptar ese caso como «sin cifrar» oculta el error.
     *
     * @param string $ruta PDF.
     *
     * @throws IngestaRechazadaException pdf_protegido o pdf_no_normalizable
     *
     * @return void
     */
    public function rechazarSiProtegido(string $ruta): void
    {
        // qpdf --requires-password: 0 = necesita contraseña, 2 = sin cifrar (o error), 3 = cifrado sin contraseña de usuario.
        $comando = [(string) config('documentos.binarios.qpdf'), '--requires-password', $ruta];
        $resultado = $this->ejecutar($comando);

        match (true) {
            $resultado->exitCode() === 0 => throw new IngestaRechazadaException('pdf_protegido'),
            $resultado->exitCode() === 3 => null,
            $resultado->exitCode() === 2 && trim($resultado->errorOutput()) === '' => null,
            default => throw $this->rechazo($comando, $resultado, 'qpdf no ha podido determinar si el PDF pide contraseña'),
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
        $comando = [(string) config('documentos.binarios.pdfinfo'), $ruta];
        $resultado = $this->ejecutar($comando);

        if ($resultado->failed() || ! preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $coincidencia)) {
            throw $this->rechazo($comando, $resultado, 'pdfinfo no ha devuelto el número de páginas');
        }

        return (int) $coincidencia[1];
    }

    /**
     * Tinta del documento entero: suma de la cobertura C+M+Y+K de todas sus páginas.
     *
     * Sirve para detectar un PDF sin contenido visible (dañado y «reparado» en blanco
     * por Ghostscript, o en blanco sin más). Se mide el documento completo, de modo que
     * una página en blanco entre otras con contenido no lo deja por debajo del umbral.
     * No reescribe el fichero: se puede aplicar a PDF firmados.
     *
     * @param string $ruta PDF.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable si Ghostscript no puede medirlo
     *
     * @return float Cobertura total, en fracción de la superficie de una página.
     */
    public function tintaTotal(string $ruta): float
    {
        $comando = [(string) config('documentos.binarios.gs'), '-q', '-dSAFER', '-o', '-', '-sDEVICE=inkcov', $ruta];
        $resultado = $this->ejecutar($comando);

        // Una línea por página: «0.00227  0.00227  0.00227  0.00227 CMYK OK».
        preg_match_all('/^\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+CMYK/m', $resultado->output(), $paginas, PREG_SET_ORDER);

        if ($resultado->failed() || $paginas === []) {
            throw $this->rechazo($comando, $resultado, 'Ghostscript no ha medido la tinta del PDF');
        }

        $total = 0.0;
        foreach ($paginas as $pagina) {
            $total += (float) $pagina[1] + (float) $pagina[2] + (float) $pagina[3] + (float) $pagina[4];
        }

        return $total;
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
        $comando = [
            (string) config('documentos.binarios.qpdf'),
            '--qdf', '--object-streams=disable', '--decode-level=generalized',
            $ruta, $expandido,
        ];
        $resultado = $this->ejecutar($comando);

        // qpdf devuelve 3 cuando termina con avisos; el fichero es válido igualmente.
        if (! in_array($resultado->exitCode(), [0, 3], true) || ! is_file($expandido)) {
            throw $this->rechazo($comando, $resultado, 'qpdf no ha generado la forma expandida del PDF');
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
        $comando = [
            (string) config('documentos.binarios.gs'),
            ...self::GS_PDFA,
            ...$extra,
            '-o', $salida,
            $ruta,
        ];
        $resultado = $this->ejecutar($comando);

        // Ghostscript puede terminar con código 0 sin generar páginas (p. ej. si no puede abrir el PDF).
        if ($resultado->failed() || ! is_file($salida) || filesize($salida) === 0) {
            throw $this->rechazo($comando, $resultado, 'Ghostscript no ha generado el PDF/A');
        }
    }

    /**
     * Ejecuta un binario con el timeout de saneado.
     *
     * @param list<string> $comando Comando y argumentos.
     *
     * @throws IngestaRechazadaException pdf_no_normalizable si se agota el tiempo o no se puede lanzar
     *
     * @return ProcessResult
     */
    private function ejecutar(array $comando): ProcessResult
    {
        try {
            return Process::timeout((int) config('documentos.ingesta.timeout_saneado_segundos'))->run($comando);
        } catch (\Throwable $e) {
            throw new IngestaRechazadaException(
                'pdf_no_normalizable',
                new FalloHerramientaExterna($comando, null, '', 'El proceso no ha terminado: '.$e->getMessage(), $e),
            );
        }
    }

    /**
     * Rechazo pdf_no_normalizable con la causa técnica del proceso adjunta.
     *
     * @param list<string> $comando Comando y argumentos ejecutados.
     * @param ProcessResult $resultado Resultado del proceso.
     * @param string $motivo Qué se esperaba y no se obtuvo.
     *
     * @return IngestaRechazadaException
     */
    private function rechazo(array $comando, ProcessResult $resultado, string $motivo): IngestaRechazadaException
    {
        return new IngestaRechazadaException(
            'pdf_no_normalizable',
            FalloHerramientaExterna::desde($comando, $resultado, $motivo),
        );
    }
}
