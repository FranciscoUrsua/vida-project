<?php

namespace Modules\Documentos\Enums;

/**
 * Qué hacer con las versiones anteriores de un documento al sustituirlo.
 *
 * Con purgar_no_retenidas, la versión sustituida sin retenciones se purga (se destruyen su objeto y su clave).
 */
enum PoliticaVersiones: string
{
    case Conservar = 'conservar';
    case PurgarNoRetenidas = 'purgar_no_retenidas';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Conservar => 'Conservar',
            self::PurgarNoRetenidas => 'Purgar si no está retenida',
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
