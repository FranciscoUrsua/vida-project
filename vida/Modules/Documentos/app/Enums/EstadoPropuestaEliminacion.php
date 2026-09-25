<?php

namespace Modules\Documentos\Enums;

/**
 * Estado de una propuesta de destrucción de versiones por plazo de conservación vencido.
 *
 * Solo una propuesta pendiente se puede aprobar o rechazar; después es definitiva.
 */
enum EstadoPropuestaEliminacion: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
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
