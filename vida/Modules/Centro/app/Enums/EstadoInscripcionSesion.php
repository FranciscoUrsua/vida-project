<?php

namespace Modules\Centro\Enums;

use App\Support\Ui\Tono;

/**
 * Estado de la inscripción de una persona en una sesión de actividad (fase 6).
 *
 * Solo `inscrita` ocupa plaza. La baja es lógica y final: para volver a la sesión se
 * crea una inscripción nueva (nada se borra, F6-5).
 */
enum EstadoInscripcionSesion: string
{
    case Inscrita = 'inscrita';
    case Baja = 'baja';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Inscrita => 'Inscrita',
            self::Baja => 'Baja',
        };
    }

    /**
     * Tono de color del estado.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Inscrita => Tono::Exito,
            self::Baja => Tono::Neutro,
        };
    }
}
