<?php

namespace Modules\Prestaciones\Data;

use Illuminate\Support\Carbon;

/**
 * Lo que se sabe de un expediente en un momento: estado externo, incidencias nuevas y, si la
 * hay, la resolución (RN-07). Es la entrada única de SolicitudPrestacionService::registrarActualizacion().
 */
final readonly class ActualizacionExpediente
{
    /**
     * @param string|null $estadoCodigo Código del estado en el gestor (se guarda tal cual).
     * @param string|null $estadoTexto Texto del estado en el gestor.
     * @param Carbon $fecha Fecha de la actualización.
     * @param list<IncidenciaExpediente> $incidencias Incidencias nuevas.
     * @param ResolucionDto|null $resolucion Resolución, si la hay.
     * @param string|null $estadoNormalizado Normalizado ya resuelto (registro manual); si no, por configuración.
     */
    public function __construct(
        public ?string $estadoCodigo,
        public ?string $estadoTexto,
        public Carbon $fecha,
        public array $incidencias = [],
        public ?ResolucionDto $resolucion = null,
        public ?string $estadoNormalizado = null,
    ) {}
}
