<?php

namespace Modules\Agenda\Enums;

/**
 * Para quién se pide la cita: decide cómo se buscan los huecos (docs/modulo-citas.md §3.3).
 */
enum DestinoCita: string
{
    case ProfesionalConcreto = 'profesional_concreto';
    case Referencia = 'referencia';
    case Servicio = 'servicio';
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
            self::Servicio => 'Servicio o perfil',
            self::PrimerLibre => 'Primer profesional libre',
        };
    }
}
