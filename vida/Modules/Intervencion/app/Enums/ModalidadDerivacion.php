<?php

namespace Modules\Intervencion\Enums;

/**
 * Modalidad de una derivación: decide qué se abre al aceptarla.
 *
 * - `PlanEspecializado` (fase 3): al aceptarla se abre un plan especializado en el
 *   centro de destino; nace del plan principal con una prestación de derivación.
 * - `Encargo` (encargo profesional, EP-1): un profesional del centro de destino hace un
 *   trabajo puntual con la persona sin acceso a la Historia Social y lo cierra con un
 *   resultado; no abre plan.
 */
enum ModalidadDerivacion: string
{
    case PlanEspecializado = 'plan_especializado';
    case Encargo = 'encargo';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PlanEspecializado => 'Atención especializada',
            self::Encargo => 'Encargo profesional',
        };
    }
}
