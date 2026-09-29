<?php

namespace Modules\Intervencion\Enums;

use App\Support\Ui\Tono;

/**
 * Tipos de apunte registrados en la historia social.
 */
enum TipoApunte: string
{
    case Entrevista = 'entrevista';
    case Documento = 'documento';
    case Derivacion = 'derivacion';
    case Seguimiento = 'seguimiento';
    case Anotacion = 'anotacion';
    // Tipos añadidos para el interfaz operativo (Entrega 3)
    case Valoracion = 'valoracion';
    case Escala = 'escala';
    case GestionCoordinacion = 'gestion_coordinacion';
    case PlanIntervencion = 'plan_intervencion';

    /**
     * Etiqueta legible para mostrar el tipo de apunte.
     */
    public function label(): string
    {
        return match ($this) {
            self::Entrevista => 'Entrevista',
            self::Documento => 'Documento',
            self::Derivacion => 'Derivación',
            self::Seguimiento => 'Seguimiento',
            self::Anotacion => 'Anotación',
            self::Valoracion => 'Valoración',
            self::Escala => 'Escala',
            self::GestionCoordinacion => 'Gestión / coordinación',
            self::PlanIntervencion => 'Plan de intervención',
        };
    }

    /**
     * Color del punto del apunte en la línea de tiempo de la historia.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Entrevista, self::Escala, self::Seguimiento => Tono::Primario,
            self::Valoracion, self::Derivacion => Tono::Exito,
            self::PlanIntervencion => Tono::Aviso,
            self::Anotacion, self::GestionCoordinacion, self::Documento => Tono::Neutro,
        };
    }
}
