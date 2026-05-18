<?php

namespace Modules\Intervencion\Enums;

enum TipoPlan: string
{
    case GeneralAsp   = 'general_asp';
    case Especializado = 'especializado';

    public function label(): string
    {
        return match ($this) {
            self::GeneralAsp   => 'Plan general ASP',
            self::Especializado => 'Plan especializado',
        };
    }
}
