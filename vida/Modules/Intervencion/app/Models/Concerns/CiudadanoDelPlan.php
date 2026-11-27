<?php

namespace Modules\Intervencion\Models\Concerns;

use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Ciudadano de un registro que cuelga de un plan de intervención (objetivos, actuaciones,
 * participantes, fichas del diagnóstico, seguimientos, PISO firmado), para la auditoría.
 *
 * El plan se lee sin scopes: la auditoría tiene que encontrar a la persona aunque el plan
 * quede fuera del ámbito de quien escribe.
 */
trait CiudadanoDelPlan
{
    /**
     * Ciudadano del plan al que pertenece el registro.
     *
     * @return int|null
     */
    public function getCiudadanoId(): ?int
    {
        $planId = $this->getAttribute($this->columnaPlanAuditoria());

        return $planId === null
            ? null
            : PlanDeIntervencion::withoutGlobalScopes()->find($planId)?->getCiudadanoId();
    }

    /**
     * Columna con el id del plan. Por defecto `plan_id`.
     *
     * @return string
     */
    protected function columnaPlanAuditoria(): string
    {
        return 'plan_id';
    }
}
