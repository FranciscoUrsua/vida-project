<?php

namespace App\Events;

use App\Models\Ciudadano;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La dirección de un ciudadano se acaba de normalizar (y tiene, si el
 * geocodificador los devolvió, sus códigos territoriales).
 *
 * Lo disparan DireccionObserver y NormalizarDireccionJob. Lo escucha la
 * asignación de centro por domicilio (docs/modulo-asignacion.md §3.5).
 */
class DireccionCiudadanoNormalizada
{
    use Dispatchable;

    /**
     * @param Ciudadano $ciudadano Ciudadano con la dirección ya normalizada.
     */
    public function __construct(
        public readonly Ciudadano $ciudadano,
    ) {}
}
