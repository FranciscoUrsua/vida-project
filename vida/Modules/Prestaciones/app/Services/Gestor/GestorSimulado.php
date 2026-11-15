<?php

namespace Modules\Prestaciones\Services\Gestor;

use Illuminate\Support\Carbon;
use Modules\Prestaciones\Data\ActualizacionExpediente;
use Modules\Prestaciones\Data\SolicitudParaGestor;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Services\SolicitudPrestacionService;
use RuntimeException;

/**
 * Simulador del gestor de expedientes para desarrollo y tests (§4 del diseño).
 *
 * Números de expediente deterministas (`SIM-{id de la solicitud}`), estados y resoluciones
 * programables (`programar()`), fallos programables (`fallarEn()`) y avisos simulados que
 * pasan por SolicitudPrestacionService::procesarAviso(), como lo haría el endpoint real.
 */
class GestorSimulado implements AdaptadorGestorExpedientes
{
    /** @var array<string, ActualizacionExpediente> Respuesta programada por expediente */
    private array $respuestas = [];

    /** @var array<string, true> Operaciones o expedientes que fallan */
    private array $fallos = [];

    /** @var list<array{expediente: string, documentos: list<int>}> Llamadas a aportarDocumentacion */
    public array $aportaciones = [];

    /** @var list<int> Solicitudes iniciadas */
    public array $iniciadas = [];

    /**
     * Inicia el expediente con un número determinista.
     *
     * @param SolicitudParaGestor $solicitud
     *
     * @return string
     *
     * @throws RuntimeException Si se programó un fallo.
     */
    public function iniciarSolicitud(SolicitudParaGestor $solicitud): string
    {
        if (isset($this->fallos[self::INICIAR])) {
            throw new RuntimeException('El gestor simulado no responde (fallo programado).');
        }

        $this->iniciadas[] = $solicitud->solicitudId;

        return 'SIM-'.$solicitud->solicitudId;
    }

    /**
     * Devuelve la actualización programada o «en trámite».
     *
     * @param string $expediente
     *
     * @return ActualizacionExpediente
     *
     * @throws RuntimeException Si se programó un fallo para ese expediente.
     */
    public function consultarEstado(string $expediente): ActualizacionExpediente
    {
        if (isset($this->fallos[$expediente])) {
            throw new RuntimeException("El gestor simulado falla al consultar {$expediente}.");
        }

        return $this->respuestas[$expediente] ?? new ActualizacionExpediente('TRAMITE_01', 'En trámite', Carbon::today());
    }

    /**
     * Registra la aportación.
     *
     * @param string $expediente
     * @param list<int> $documentoIds
     *
     * @return void
     */
    public function aportarDocumentacion(string $expediente, array $documentoIds): void
    {
        $this->aportaciones[] = ['expediente' => $expediente, 'documentos' => $documentoIds];
    }

    /**
     * Admite todas las operaciones.
     *
     * @param string $operacion
     *
     * @return bool
     */
    public function admite(string $operacion): bool
    {
        return true;
    }

    /**
     * Nombre del adaptador.
     *
     * @return string
     */
    public function nombre(): string
    {
        return 'simulado';
    }

    /**
     * Programa la respuesta de `consultarEstado()` para un expediente.
     *
     * @param string $expediente
     * @param ActualizacionExpediente $actualizacion
     *
     * @return void
     */
    public function programar(string $expediente, ActualizacionExpediente $actualizacion): void
    {
        $this->respuestas[$expediente] = $actualizacion;
    }

    /**
     * Programa un fallo: `iniciar` para el envío, o un número de expediente para su consulta.
     *
     * @param string $operacionOExpediente
     *
     * @return void
     */
    public function fallarEn(string $operacionOExpediente): void
    {
        $this->fallos[$operacionOExpediente] = true;
    }

    /**
     * Simula un aviso entrante del gestor sobre la solicitud.
     *
     * @param SolicitudPrestacion $solicitud
     * @param ActualizacionExpediente $actualizacion
     *
     * @return void
     */
    public function simularAviso(SolicitudPrestacion $solicitud, ActualizacionExpediente $actualizacion): void
    {
        app(SolicitudPrestacionService::class)->procesarAviso((string) $solicitud->numero_expediente, $actualizacion);
    }
}
