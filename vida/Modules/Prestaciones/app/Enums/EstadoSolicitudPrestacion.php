<?php

namespace Modules\Prestaciones\Enums;

use App\Support\Ui\Tono;

/**
 * Estados de una solicitud de prestación (docs/modulo-solicitudes-expedientes.md §2.2).
 *
 * Resuelta, cerrada sin resolución y anulada son finales. Cada cambio deja un evento
 * inmutable en `solicitud_prestacion_eventos`.
 */
enum EstadoSolicitudPrestacion: string
{
    case Borrador = 'borrador';
    case EnBandeja = 'en_bandeja';
    case EnPreparacion = 'en_preparacion';
    case Enviada = 'enviada';
    case Resuelta = 'resuelta';
    case CerradaSinResolucion = 'cerrada_sin_resolucion';
    case Anulada = 'anulada';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnBandeja => 'En bandeja',
            self::EnPreparacion => 'En preparación',
            self::Enviada => 'Enviada',
            self::Resuelta => 'Resuelta',
            self::CerradaSinResolucion => 'Cerrada sin resolución',
            self::Anulada => 'Anulada',
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
            self::Borrador => Tono::Neutro,
            self::EnBandeja, self::EnPreparacion => Tono::Info,
            self::Enviada => Tono::Primario,
            self::Resuelta => Tono::Exito,
            self::CerradaSinResolucion => Tono::Aviso,
            self::Anulada => Tono::Neutro,
        };
    }

    /**
     * Si todavía no se ha enviado (se puede anular).
     *
     * @return bool
     */
    public function antesDelEnvio(): bool
    {
        return in_array($this, [self::Borrador, self::EnBandeja, self::EnPreparacion], true);
    }

    /**
     * Si la solicitud está en curso (ni resuelta, ni cerrada, ni anulada).
     *
     * @return bool
     */
    public function enCurso(): bool
    {
        return in_array($this, [self::Borrador, self::EnBandeja, self::EnPreparacion, self::Enviada], true);
    }
}
