<?php

namespace Modules\Intervencion\Enums;

enum UrgenciaSia: string
{
    case Urgencia = 'urgencia';
    case Prioritario = 'prioritario';
    case Ordinario = 'ordinario';

    public function label(): string
    {
        return match ($this) {
            self::Urgencia => 'Urgencia (24h)',
            self::Prioritario => 'Prioritario (5 días hábiles)',
            self::Ordinario => 'Ordinario (15 días hábiles)',
        };
    }
}
