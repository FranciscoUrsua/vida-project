<?php

namespace Modules\Intervencion\Enums;

/**
 * Niveles de urgencia aplicables en SIA.
 */
enum UrgenciaSia: string
{
    case Urgencia = 'urgencia';
    case Prioritario = 'prioritario';
    case Ordinario = 'ordinario';

    /**
     * Devuelve la etiqueta legible del nivel de urgencia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Urgencia => 'Urgencia (24h)',
            self::Prioritario => 'Prioritario (5 días hábiles)',
            self::Ordinario => 'Ordinario (15 días hábiles)',
        };
    }
}
