<?php

namespace Modules\Centro\Enums;

use App\Support\Ui\Tono;

/**
 * Por qué el sistema no pudo asignar solo (RN-09: nada ambiguo se asigna automáticamente).
 *
 * @see docs/modulo-asignacion.md §3.2 y §4.2
 */
enum MotivoAsignacionPendiente: string
{
    /** La dirección no tiene códigos territoriales (no se pudo geocodificar). */
    case SinCodigos = 'sin_codigos';
    /** Ningún centro del tipo incluye la dirección. */
    case SinCobertura = 'sin_cobertura';
    /** Más de un centro del tipo la incluye en el mismo nivel. */
    case Ambiguo = 'ambiguo';
    /** El centro no tiene profesionales en el reparto. */
    case SinElegibles = 'sin_elegibles';

    /**
     * Etiqueta legible del motivo.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::SinCodigos => 'Dirección sin geocodificar',
            self::SinCobertura => 'Fuera de ámbito',
            self::Ambiguo => 'Encaja en varios centros',
            self::SinElegibles => 'Sin profesionales en el reparto',
        };
    }

    /**
     * Color del distintivo del motivo en la bandeja.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::SinCodigos, self::SinCobertura => Tono::Aviso,
            self::Ambiguo => Tono::Primario,
            self::SinElegibles => Tono::Peligro,
        };
    }
}
