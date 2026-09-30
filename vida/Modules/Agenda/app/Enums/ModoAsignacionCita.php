<?php

namespace Modules\Agenda\Enums;

/**
 * Cómo se asignó el profesional de una cita. «Sustituto» significa que se pidió
 * para la referencia y esta no estaba disponible; la referencia no cambia.
 */
enum ModoAsignacionCita: string
{
    case ProfesionalConcreto = 'profesional_concreto';
    case Referencia = 'referencia';
    case Sustituto = 'sustituto';
    case PrimerLibre = 'primer_libre';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ProfesionalConcreto => 'Profesional concreto',
            self::Referencia => 'Profesional de referencia',
            self::Sustituto => 'Sustituto de la referencia',
            self::PrimerLibre => 'Primer libre',
        };
    }
}
