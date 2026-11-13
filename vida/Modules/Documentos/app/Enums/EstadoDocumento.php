<?php

namespace Modules\Documentos\Enums;

/**
 * Estado del documento lógico.
 *
 * «Caducado» no es un estado: se calcula a partir de fecha_validez. En esta fase todo entra vigente;
 * pendiente_validacion queda reservado para el futuro portal del ciudadano.
 */
enum EstadoDocumento: string
{
    case PendienteValidacion = 'pendiente_validacion';
    case Vigente = 'vigente';
    case Rechazado = 'rechazado';
    case Destruido = 'destruido';

    /**
     * Etiqueta legible para la interfaz.
     *
     * @return string
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PendienteValidacion => 'Pendiente de validación',
            self::Vigente => 'Vigente',
            self::Rechazado => 'Rechazado',
            self::Destruido => 'Destruido',
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
