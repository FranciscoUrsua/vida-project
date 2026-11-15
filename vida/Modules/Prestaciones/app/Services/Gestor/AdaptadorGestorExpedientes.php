<?php

namespace Modules\Prestaciones\Services\Gestor;

use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\SolicitudParaGestor;

/**
 * Contrato con el gestor de expedientes externo (§4 del diseño; modulo-integraciones.md §4.3).
 *
 * VIDA es agnóstico respecto al gestor: hoy no existe y se usa GestorManual (registro manual);
 * GestorSimulado sirve para desarrollo y tests. El adaptador real se ligará en el provider
 * por `config('prestaciones.gestor_expedientes')`. El aviso entrante (endpoint) está fuera de
 * alcance: lo procesa SolicitudPrestacionService::procesarAviso().
 */
interface AdaptadorGestorExpedientes
{
    /** Operaciones que puede admitir un adaptador. */
    public const INICIAR = 'iniciar';

    public const CONSULTAR = 'consultar';

    public const APORTAR = 'aportar';

    /**
     * Inicia el expediente y devuelve su número.
     *
     * @param SolicitudParaGestor $solicitud
     *
     * @return string
     */
    public function iniciarSolicitud(SolicitudParaGestor $solicitud): string;

    /**
     * Estado actual del expediente, incidencias nuevas y resolución si la hay.
     *
     * @param string $expediente Número de expediente.
     *
     * @return ActualizacionExpediente
     */
    public function consultarEstado(string $expediente): ActualizacionExpediente;

    /**
     * Envía documentos para atender una incidencia.
     *
     * @param string $expediente
     * @param list<int> $documentoIds
     *
     * @return void
     */
    public function aportarDocumentacion(string $expediente, array $documentoIds): void;

    /**
     * Si el adaptador admite la operación (INICIAR, CONSULTAR o APORTAR).
     *
     * @param string $operacion
     *
     * @return bool
     */
    public function admite(string $operacion): bool;

    /**
     * Nombre del adaptador (clave de configuración y del mapeo de estados).
     *
     * @return string
     */
    public function nombre(): string;
}
