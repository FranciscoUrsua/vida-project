<?php

namespace Modules\Documentos\Enums;

/**
 * Estado de una versión de documento. Solo una versión por documento puede estar vigente.
 */
enum EstadoVersion: string
{
    case Vigente = 'vigente';
    case Sustituida = 'sustituida';
    case Purgada = 'purgada';
    case Destruida = 'destruida';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Sustituida => 'Sustituida',
            self::Purgada => 'Purgada',
            self::Destruida => 'Destruida',
        };
    }

    /**
     * Valores almacenables, para validaciones y restricciones CHECK.
     *
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
