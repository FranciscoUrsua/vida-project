<?php

namespace Modules\Documentos\Services\Ingesta;

use Modules\Documentos\Contracts\EscanerAntivirus;
use Modules\Documentos\Exceptions\AntivirusNoDisponibleException;

/**
 * Escáner antivirus sobre clamd, por su socket local y con el protocolo INSTREAM.
 *
 * El contenido se envía por el socket en lugar de pasar la ruta, porque clamd se
 * ejecuta con su propio usuario y no puede leer la zona temporal de ingesta (0600).
 */
class EscanerClamAv implements EscanerAntivirus
{
    /** Tamaño de cada trozo enviado a clamd. */
    private const TROZO = 8192;

    /**
     * {@inheritDoc}
     */
    public function escanear(string $ruta): ?string
    {
        $fichero = @fopen($ruta, 'rb');
        if ($fichero === false) {
            throw new AntivirusNoDisponibleException('No se puede leer el fichero que hay que analizar.');
        }

        $socket = $this->conectar();

        try {
            $this->escribir($socket, "zINSTREAM\0");

            while (! feof($fichero)) {
                $trozo = (string) fread($fichero, self::TROZO);
                if ($trozo !== '') {
                    $this->escribir($socket, pack('N', strlen($trozo)).$trozo);
                }
            }
            // Un trozo de longitud cero cierra el flujo.
            $this->escribir($socket, pack('N', 0));

            $respuesta = rtrim((string) stream_get_contents($socket), "\0\r\n");
            if (stream_get_meta_data($socket)['timed_out']) {
                throw new AntivirusNoDisponibleException('clamd no ha respondido a tiempo.');
            }
        } finally {
            fclose($fichero);
            fclose($socket);
        }

        return $this->interpretar($respuesta);
    }

    /**
     * Abre el socket de clamd con el timeout configurado.
     *
     * @throws AntivirusNoDisponibleException
     *
     * @return resource
     */
    private function conectar()
    {
        $timeout = (int) config('documentos.ingesta.timeout_antivirus_segundos');
        $socket = @stream_socket_client(
            'unix://'.config('documentos.binarios.clamd_socket'),
            $codigo,
            $mensaje,
            $timeout,
        );

        if ($socket === false) {
            throw new AntivirusNoDisponibleException("No se puede conectar con clamd: {$mensaje}");
        }

        stream_set_timeout($socket, $timeout);

        return $socket;
    }

    /**
     * Escribe en el socket o falla.
     *
     * @param resource $socket Socket de clamd.
     * @param string $datos Datos que se envían.
     *
     * @throws AntivirusNoDisponibleException
     *
     * @return void
     */
    private function escribir($socket, string $datos): void
    {
        if (@fwrite($socket, $datos) !== strlen($datos)) {
            throw new AntivirusNoDisponibleException('clamd ha cortado la conexión.');
        }
    }

    /**
     * Traduce la respuesta de clamd: «stream: OK», «stream: <firma> FOUND» o un error.
     *
     * @param string $respuesta Respuesta sin terminadores.
     *
     * @throws AntivirusNoDisponibleException ante cualquier respuesta que no sea OK ni FOUND
     *
     * @return string|null
     */
    private function interpretar(string $respuesta): ?string
    {
        if ($respuesta === 'stream: OK') {
            return null;
        }

        if (preg_match('/^stream: (.+) FOUND$/', $respuesta, $coincidencia)) {
            return $coincidencia[1];
        }

        // Incluye «INSTREAM size limit exceeded. ERROR» (StreamMaxLength de clamd).
        throw new AntivirusNoDisponibleException("Respuesta inesperada de clamd: {$respuesta}");
    }
}
