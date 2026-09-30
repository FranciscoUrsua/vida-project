<?php

namespace Modules\Agenda\Services\Citas\CitaPrevia;

use Modules\Agenda\Models\Cita;

/**
 * Contrato con el sistema de Cita Previa del Ayuntamiento (principio 3.6).
 *
 * El contrato real (mapeo de servicios, datos de identificación, reconciliación)
 * está pendiente de la integración (docs/modulo-citas.md §11). Hasta entonces el
 * adaptador activo es el mock.
 */
interface AdaptadorCitaPrevia
{
    /**
     * Traduce una petición del sistema externo a los datos de VIDA.
     *
     * @param array<string, mixed> $payload
     * @return CitaExternaRecibida
     */
    public function interpretar(array $payload): CitaExternaRecibida;

    /**
     * Notifica al sistema externo un cambio en una cita suya (reprogramación, cancelación).
     *
     * @param Cita $cita Cita afectada (la original).
     * @param string $accion reprogramada | cancelada
     * @param array<string, mixed> $datos Sin datos personales.
     * @return void
     */
    public function notificar(Cita $cita, string $accion, array $datos = []): void;
}
