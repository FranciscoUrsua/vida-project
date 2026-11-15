<?php

namespace Modules\Prestaciones\Data;

use Illuminate\Support\Carbon;
use Modules\Prestaciones\Enums\SentidoResolucion;

/**
 * Resolución de un expediente tal como llega del gestor o del registro manual.
 */
final readonly class ResolucionDto
{
    /**
     * @param Carbon $fecha
     * @param SentidoResolucion $sentido
     * @param list<LineaResolucionDto> $lineas
     */
    public function __construct(
        public Carbon $fecha,
        public SentidoResolucion $sentido,
        public array $lineas,
    ) {}
}
