<?php

namespace Modules\Agenda\Enums;

/**
 * Cómo se presta la cita. Mismos valores que `entrevistas.modalidad`.
 */
enum ModalidadCita: string
{
    case Presencial = 'presencial';
    case Telefonica = 'telefonica';
    case Videollamada = 'videollamada';
    case Domicilio = 'domicilio';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Telefonica => 'Telefónica',
            self::Videollamada => 'Videollamada',
            self::Domicilio => 'Visita a domicilio',
        };
    }
}
