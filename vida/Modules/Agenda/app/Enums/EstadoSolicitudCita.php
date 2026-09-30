<?php

namespace Modules\Agenda\Enums;

use App\Support\Ui\Tono;

/**
 * Estados de una solicitud de cita. Citada, desistida y anulada son finales;
 * las transiciones válidas las fija puedePasarA() y las comprueba el modelo.
 */
enum EstadoSolicitudCita: string
{
    case Pendiente = 'pendiente';
    case EnGestion = 'en_gestion';
    case Citada = 'citada';
    case Desistida = 'desistida';
    case Anulada = 'anulada';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnGestion => 'En gestión',
            self::Citada => 'Citada',
            self::Desistida => 'Desistida',
            self::Anulada => 'Anulada',
        };
    }

    /**
     * Si es un estado final (no admite más cambios).
     *
     * @return bool
     */
    public function esFinal(): bool
    {
        return in_array($this, [self::Citada, self::Desistida, self::Anulada], true);
    }

    /**
     * Si la transición a otro estado es válida.
     *
     * @param self $destino
     * @return bool
     */
    public function puedePasarA(self $destino): bool
    {
        return match ($this) {
            self::Pendiente => in_array($destino, [self::EnGestion, self::Citada, self::Desistida, self::Anulada], true),
            self::EnGestion => in_array($destino, [self::Pendiente, self::Citada, self::Desistida, self::Anulada], true),
            default => false,
        };
    }

    /**
     * Tono con que se pinta el estado.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Pendiente => Tono::Aviso,
            self::EnGestion => Tono::Info,
            self::Citada => Tono::Exito,
            self::Desistida, self::Anulada => Tono::Neutro,
        };
    }
}
