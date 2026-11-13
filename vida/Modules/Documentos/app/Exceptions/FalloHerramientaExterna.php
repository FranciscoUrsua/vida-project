<?php

namespace Modules\Documentos\Exceptions;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

/**
 * Causa técnica de un fallo de un binario externo de la tubería (qpdf, Ghostscript,
 * pdfinfo, LibreOffice).
 *
 * Nunca se muestra al profesional: va como excepción previa de IngestaRechazadaException
 * para que el registro conserve el comando, el código de salida y la salida de error.
 * Del binario solo se guarda el nombre; los argumentos son parámetros y rutas de la
 * zona temporal de ingesta, con nombres neutros y sin datos personales. La salida de
 * error se recorta a sus primeros 2.000 caracteres.
 */
class FalloHerramientaExterna extends RuntimeException
{
    /** Máximo de caracteres de la salida de error que se conservan. */
    public const MAX_SALIDA_ERROR = 2000;

    /** @var list<string> Nombre del binario y argumentos ejecutados. */
    public readonly array $comando;

    /** Salida de error del proceso, recortada a MAX_SALIDA_ERROR caracteres. */
    public readonly string $salidaError;

    /**
     * Crea la causa a partir de los datos del proceso.
     *
     * @param list<string> $comando Comando y argumentos ejecutados; del binario se queda el nombre.
     * @param int|null $codigoSalida Código de salida; null si el proceso no llegó a terminar (timeout).
     * @param string $salidaError Salida de error del proceso.
     * @param string $motivo Qué se esperaba y no se obtuvo.
     * @param \Throwable|null $previa Excepción original (p. ej. el timeout de Symfony Process).
     */
    public function __construct(
        array $comando,
        public readonly ?int $codigoSalida,
        string $salidaError,
        string $motivo,
        ?\Throwable $previa = null,
    ) {
        if ($comando !== []) {
            $comando[0] = basename($comando[0]);
        }
        $this->comando = $comando;
        $this->salidaError = mb_substr(trim($salidaError), 0, self::MAX_SALIDA_ERROR);

        parent::__construct(sprintf(
            '%s. Comando: %s. Código de salida: %s. Salida de error: %s',
            $motivo,
            implode(' ', array_map('escapeshellarg', $this->comando)),
            $codigoSalida ?? 'ninguno',
            $this->salidaError === '' ? '(vacía)' : $this->salidaError,
        ), 0, $previa);
    }

    /**
     * Crea la causa a partir del resultado de un proceso terminado.
     *
     * @param list<string> $comando Comando y argumentos ejecutados.
     * @param ProcessResult $resultado Resultado del proceso.
     * @param string $motivo Qué se esperaba y no se obtuvo.
     *
     * @return self
     */
    public static function desde(array $comando, ProcessResult $resultado, string $motivo): self
    {
        return new self($comando, $resultado->exitCode(), $resultado->errorOutput(), $motivo);
    }
}
