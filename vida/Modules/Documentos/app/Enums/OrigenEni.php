<?php

namespace Modules\Documentos\Enums;

/**
 * Origen del documento según el Esquema Nacional de Interoperabilidad (ciudadano o administración).
 */
enum OrigenEni: string
{
    case Ciudadano = 'ciudadano';
    case Administracion = 'administracion';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Ciudadano => 'Ciudadano',
            self::Administracion => 'Administración',
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
