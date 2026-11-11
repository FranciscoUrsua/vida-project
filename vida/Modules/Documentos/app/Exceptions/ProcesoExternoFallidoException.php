<?php

namespace Modules\Documentos\Exceptions;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

/**
 * Causa técnica de un fallo de un binario externo de la tubería (qpdf, Ghostscript, pdfinfo).
 *
 * Nunca se muestra al profesional: va como excepción previa de IngestaRechazadaException
 * para que el registro conserve el comando, el código de salida y la salida de error.
 * Los argumentos del comando son rutas de la zona temporal, sin datos personales.
 */
class ProcesoExternoFallidoException extends RuntimeException
{
    /**
     * Crea la causa a partir de los datos del proceso.
     *
     * @param list<string> $comando Comando y argumentos ejecutados.
     * @param int|null $codigoSalida Código de salida; null si el proceso no llegó a terminar (timeout).
     * @param string $salidaError Salida de error del proceso.
     * @param string $motivo Qué se esperaba y no se obtuvo.
     * @param \Throwable|null $previa Excepción original (p. ej. el timeout de Symfony Process).
     */
    public function __construct(
        public readonly array $comando,
        public readonly ?int $codigoSalida,
        public readonly string $salidaError,
        string $motivo,
        ?\Throwable $previa = null,
    ) {
        parent::__construct(sprintf(
            '%s. Comando: %s. Código de salida: %s. Salida de error: %s',
            $motivo,
            implode(' ', array_map('escapeshellarg', $comando)),
            $codigoSalida ?? 'ninguno',
            trim($salidaError) === '' ? '(vacía)' : trim($salidaError),
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
