<?php

namespace Modules\Centro\Enums;

/**
 * Cómo asigna un centro el profesional de referencia al abrir una Historia Social.
 *
 * Es configuración del centro. No confundir con OrigenAsignacionReferencia,
 * que registra cómo se hizo cada asignación concreta y tiene más valores
 * (unidad de convivencia, reparto, manual).
 *
 * @see docs/modulo-asignacion.md RN-03
 */
enum ModoAsignacionReferenciaCentro: string
{
    case Sorteo = 'sorteo';
    case LibreEleccion = 'libre_eleccion';
    case QuienAbre = 'quien_abre';

    /**
     * Etiqueta legible del modo.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Sorteo => 'Sorteo proporcional a la jornada',
            self::LibreEleccion => 'Libre elección de la persona (sorteo si no elige)',
            self::QuienAbre => 'Sin asignación automática (quien abre la historia)',
        };
    }

    /**
     * Opciones para selects de Filament.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(
            array_map(fn (self $modo) => ['v' => $modo->value, 'l' => $modo->label()], self::cases()),
            'l',
            'v',
        );
    }
}
