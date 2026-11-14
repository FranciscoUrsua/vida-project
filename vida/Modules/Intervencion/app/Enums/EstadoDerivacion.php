<?php

namespace Modules\Intervencion\Enums;

use App\Support\Ui\Tono;

/**
 * Estados de una derivación a atención especializada (fase 3, F3-6).
 *
 * Solo la pendiente admite cambios: el centro de destino la acepta (abre el plan
 * especializado) o la rechaza, y quien derivó puede anularla. Aceptada, rechazada y
 * anulada son finales. Cada cambio deja un evento inmutable en `derivacion_eventos`.
 */
enum EstadoDerivacion: string
{
    case Pendiente = 'pendiente';
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';
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
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
            self::Anulada => 'Anulada',
        };
    }

    /**
     * Tono de color del estado en la interfaz.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Pendiente => Tono::Aviso,
            self::Aceptada => Tono::Exito,
            self::Rechazada => Tono::Peligro,
            self::Anulada => Tono::Neutro,
        };
    }
}
