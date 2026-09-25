<?php

namespace Modules\Documentos\Contracts;

use Modules\Documentos\Exceptions\AntivirusNoDisponibleException;

/**
 * Escáner antivirus de la tubería de entrada.
 *
 * Un error del escáner nunca equivale a «limpio»: quien lo use debe rechazar el
 * fichero si se lanza AntivirusNoDisponibleException.
 */
interface EscanerAntivirus
{
    /**
     * Analiza un fichero local.
     *
     * @param string $ruta Ruta del fichero en la zona temporal de ingesta.
     *
     * @throws AntivirusNoDisponibleException si el escáner no responde o devuelve un error
     *
     * @return string|null Nombre de la firma detectada, o null si el fichero está limpio.
     */
    public function escanear(string $ruta): ?string;
}
