<?php

namespace Modules\Centro\Services\Asignacion;

use Illuminate\Support\Collection;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Models\Centro;

/**
 * Resultado de resolver el centro de una dirección: o un centro inequívoco, o
 * el motivo por el que no se puede asignar solo.
 */
final class ResultadoResolucionCentro
{
    /**
     * @param Centro|null $centro Centro resuelto, si es inequívoco.
     * @param MotivoAsignacionPendiente|null $motivoPendiente Motivo si no hay centro.
     * @param Collection<int, Centro> $candidatos Centros empatados (motivo ambiguo).
     * @param string|null $seccionCensal Sección de la dirección con la que se resolvió.
     */
    public function __construct(
        public readonly ?Centro $centro,
        public readonly ?MotivoAsignacionPendiente $motivoPendiente,
        public readonly Collection $candidatos,
        public readonly ?string $seccionCensal,
    ) {}

    /**
     * Resultado con un único centro.
     *
     * @param Centro $centro
     * @param string|null $seccionCensal
     * @return self
     */
    public static function centro(Centro $centro, ?string $seccionCensal): self
    {
        return new self($centro, null, collect(), $seccionCensal);
    }

    /**
     * Resultado sin centro asignable.
     *
     * @param MotivoAsignacionPendiente $motivo
     * @param Collection<int, Centro>|null $candidatos
     * @param string|null $seccionCensal
     * @return self
     */
    public static function pendiente(MotivoAsignacionPendiente $motivo, ?Collection $candidatos = null, ?string $seccionCensal = null): self
    {
        return new self(null, $motivo, $candidatos ?? collect(), $seccionCensal);
    }
}
