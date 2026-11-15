<?php

namespace Modules\Prestaciones\Enums;

use App\Support\Ui\Tono;

/**
 * Estado de una prestación de la persona (RN-08, §2.6 del diseño).
 *
 * Concedida no es activa: la persona empieza a percibirla al registrar la fecha de inicio.
 * Finalizada es final.
 */
enum EstadoPrestacionPersona: string
{
    case Concedida = 'concedida';
    case Activa = 'activa';
    case Suspendida = 'suspendida';
    case Finalizada = 'finalizada';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Concedida => 'Concedida',
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
            self::Finalizada => 'Finalizada',
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
            self::Concedida => Tono::Info,
            self::Activa => Tono::Exito,
            self::Suspendida => Tono::Aviso,
            self::Finalizada => Tono::Neutro,
        };
    }
}
