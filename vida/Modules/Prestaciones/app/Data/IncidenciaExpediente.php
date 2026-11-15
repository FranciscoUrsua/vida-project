<?php

namespace Modules\Prestaciones\Data;

use Illuminate\Support\Carbon;

/**
 * Incidencia de un expediente que comunica el gestor o se registra a mano (§2.3 del diseño).
 */
final readonly class IncidenciaExpediente
{
    /**
     * @param string $tipo Clave del catálogo `expediente.incidencia`.
     * @param Carbon $fecha Fecha de la incidencia.
     * @param string $descripcion Descripción.
     * @param Carbon|null $plazo Fecha límite, si la hay.
     * @param string|null $idExterno Identificador en el gestor (idempotencia).
     */
    public function __construct(
        public string $tipo,
        public Carbon $fecha,
        public string $descripcion,
        public ?Carbon $plazo = null,
        public ?string $idExterno = null,
    ) {}
}
