<?php

namespace Modules\Intervencion\Enums;

enum TipoPlan: string
{
    case GeneralAsp = 'general_asp';
    case Especializado = 'especializado';

    /**
     * Devuelve la etiqueta legible del tipo de plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::GeneralAsp => 'Plan general ASP',
            self::Especializado => 'Plan especializado',
        };
    }
}
