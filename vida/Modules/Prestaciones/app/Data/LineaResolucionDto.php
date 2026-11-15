<?php

namespace Modules\Prestaciones\Data;

use Illuminate\Support\Carbon;

/**
 * Línea de una resolución tal como llega del gestor o del registro manual.
 */
final readonly class LineaResolucionDto
{
    /**
     * @param int $prestacionId Prestación resuelta (puede no ser la solicitada).
     * @param bool $concedida
     * @param string|null $motivoDenegacion Obligatorio si se deniega.
     * @param string|null $importe
     * @param string|null $intensidad
     * @param Carbon|null $fechaDesde
     * @param Carbon|null $fechaHasta
     * @param string|null $observaciones
     */
    public function __construct(
        public int $prestacionId,
        public bool $concedida,
        public ?string $motivoDenegacion = null,
        public ?string $importe = null,
        public ?string $intensidad = null,
        public ?Carbon $fechaDesde = null,
        public ?Carbon $fechaHasta = null,
        public ?string $observaciones = null,
    ) {}
}
