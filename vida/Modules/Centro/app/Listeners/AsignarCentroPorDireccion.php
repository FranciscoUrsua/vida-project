<?php

namespace Modules\Centro\Listeners;

use App\Events\DireccionCiudadanoNormalizada;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Services\Asignacion\ResolucionCentroService;

/**
 * Al normalizarse la dirección de una persona, resuelve su centro para cada
 * tipo de centro con adscripción por domicilio: la asigna si aún no tiene uno,
 * o propone el traslado si ya lo tiene y el domicilio corresponde a otro
 * (docs/modulo-asignacion.md §3.5).
 */
class AsignarCentroPorDireccion
{
    /**
     * @param AsignacionCentroService $asignacion
     * @param ResolucionCentroService $resolucion
     */
    public function __construct(
        private readonly AsignacionCentroService $asignacion,
        private readonly ResolucionCentroService $resolucion,
    ) {}

    /**
     * Evalúa la asignación de centro de la persona.
     *
     * @param DireccionCiudadanoNormalizada $evento
     * @return void
     */
    public function handle(DireccionCiudadanoNormalizada $evento): void
    {
        $ciudadano = $evento->ciudadano;

        foreach ($this->resolucion->tiposPorDomicilio() as $tipo) {
            if ($this->asignacion->vigente($ciudadano, $tipo) !== null) {
                $this->asignacion->evaluarCambioDomicilio($ciudadano, $tipo);
            } else {
                $this->asignacion->asignarPorDireccion($ciudadano, $tipo, auth()->user());
            }
        }
    }
}
