<?php

namespace Modules\Documentos\Enums;

/**
 * Familia de un tipo documental: documentación aportada por el ciudadano o informe elaborado por un profesional.
 *
 * El código decide con ella qué documentos admiten nuevas versiones (un informe firmado nunca).
 */
enum FamiliaDocumental: string
{
    case AportadoCiudadano = 'aportado_ciudadano';
    case InformeProfesional = 'informe_profesional';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::AportadoCiudadano => 'Aportado por el ciudadano',
            self::InformeProfesional => 'Informe profesional',
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
