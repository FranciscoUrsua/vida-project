<?php

namespace Modules\Intervencion\Enums;

/**
 * Tipos de entrevista del módulo de Intervención.
 */
enum TipoEntrevista: string
{
    case Inicial = 'inicial';
    case Seguimiento = 'seguimiento';
    case Urgencia = 'urgencia';
    case Informativa = 'informativa';

    /**
     * Devuelve la etiqueta legible del tipo de entrevista.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inicial => 'Inicial',
            self::Seguimiento => 'Seguimiento',
            self::Urgencia => 'Urgencia',
            self::Informativa => 'Informativa',
        };
    }
}
