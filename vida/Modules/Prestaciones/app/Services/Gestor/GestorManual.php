<?php

namespace Modules\Prestaciones\Services\Gestor;

use LogicException;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\SolicitudParaGestor;

/**
 * Adaptador por defecto mientras no haya gestor de expedientes (§4 del diseño).
 *
 * No llama a nada: `admite()` es falso para todo y la interfaz sustituye cada operación por
 * un formulario de registro manual (número de expediente, estado, incidencia, resolución).
 */
class GestorManual implements AdaptadorGestorExpedientes
{
    /**
     * Sin gestor no hay envío automático.
     *
     * @param SolicitudParaGestor $solicitud
     *
     * @return string
     *
     * @throws LogicException
     */
    public function iniciarSolicitud(SolicitudParaGestor $solicitud): string
    {
        throw new LogicException('Sin gestor de expedientes: registra el número de expediente a mano.');
    }

    /**
     * Sin gestor no hay consulta.
     *
     * @param string $expediente
     *
     * @return ActualizacionExpediente
     *
     * @throws LogicException
     */
    public function consultarEstado(string $expediente): ActualizacionExpediente
    {
        throw new LogicException('Sin gestor de expedientes: registra el estado a mano.');
    }

    /**
     * Sin gestor no se aporta documentación por esta vía.
     *
     * @param string $expediente
     * @param list<int> $documentoIds
     *
     * @return void
     */
    public function aportarDocumentacion(string $expediente, array $documentoIds): void
    {
        // Nada que hacer: tramitación la aporta en la otra aplicación.
    }

    /**
     * No admite ninguna operación automática.
     *
     * @param string $operacion
     *
     * @return bool
     */
    public function admite(string $operacion): bool
    {
        return false;
    }

    /**
     * Nombre del adaptador.
     *
     * @return string
     */
    public function nombre(): string
    {
        return 'manual';
    }
}
