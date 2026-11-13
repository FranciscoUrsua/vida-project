<?php

namespace Modules\Documentos\Enums;

/**
 * Canal por el que entró una versión de documento. «generado» corresponde a un informe firmado en VIDA.
 */
enum CanalCaptura: string
{
    case Presencial = 'presencial';
    case Escaneo = 'escaneo';
    case Generado = 'generado';
    case Portal = 'portal';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Escaneo => 'Escaneo',
            self::Generado => 'Generado por VIDA',
            self::Portal => 'Portal del ciudadano',
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
