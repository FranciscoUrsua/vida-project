<?php

namespace Modules\Intervencion\Enums;

use App\Support\Ui\Tono;

/**
 * Estado de un reparto de casos por salida de un profesional. Nada cambia en
 * las asignaciones hasta que el supervisor lo confirma (RN-08).
 */
enum EstadoRepartoCasos: string
{
    case Propuesto = 'propuesto';
    case Confirmado = 'confirmado';
    case Descartado = 'descartado';

    /**
     * Etiqueta legible del estado.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Propuesto => 'Propuesto',
            self::Confirmado => 'Confirmado',
            self::Descartado => 'Descartado',
        };
    }

    /**
     * Color del distintivo del estado.
     *
     * @return Tono
     */
    public function tono(): Tono
    {
        return match ($this) {
            self::Propuesto => Tono::Aviso,
            self::Confirmado => Tono::Exito,
            self::Descartado => Tono::Neutro,
        };
    }
}
