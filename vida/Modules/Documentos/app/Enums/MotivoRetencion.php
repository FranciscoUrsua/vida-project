<?php

namespace Modules\Documentos\Enums;

/**
 * Motivo por el que un documento o una versión no puede purgarse ni destruirse.
 *
 * Extensible: en el futuro, remisión a otra administración o requerimiento judicial.
 */
enum MotivoRetencion: string
{
    case IntervencionCerrada = 'intervencion_cerrada';
    case Manual = 'manual';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::IntervencionCerrada => 'Intervención cerrada',
            self::Manual => 'Retención manual',
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
