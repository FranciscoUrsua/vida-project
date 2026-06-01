<?php

namespace Modules\Intervencion\Enums;

enum TipoApunte: string
{
    case Entrevista        = 'entrevista';
    case Documento         = 'documento';
    case Derivacion        = 'derivacion';
    case Seguimiento       = 'seguimiento';
    case Anotacion         = 'anotacion';
    // Tipos añadidos para el interfaz operativo (Entrega 3)
    case Valoracion        = 'valoracion';
    case Escala            = 'escala';
    case GestionCoordinacion = 'gestion_coordinacion';
    case PlanIntervencion  = 'plan_intervencion';

    public function label(): string
    {
        return match ($this) {
            self::Entrevista         => 'Entrevista',
            self::Documento          => 'Documento',
            self::Derivacion         => 'Derivación',
            self::Seguimiento        => 'Seguimiento',
            self::Anotacion          => 'Anotación',
            self::Valoracion         => 'Valoración',
            self::Escala             => 'Escala',
            self::GestionCoordinacion => 'Gestión / coordinación',
            self::PlanIntervencion   => 'Plan de intervención',
        };
    }
}
