<?php

namespace Modules\Supervision\Support\Ui;

use App\Support\Ui\Tono;

/**
 * Tono de color de los valores de Supervisión que no tienen enum propio.
 * Las clases salen de {@see Tono}.
 */
final class Tonos
{
    /**
     * Badge de estado de una sesión de actividad.
     *
     * @param string $estado programada|celebrada|cancelada
     * @return Tono
     */
    public static function estadoSesion(string $estado): Tono
    {
        return match ($estado) {
            'programada' => Tono::Primario,
            'celebrada' => Tono::Exito,
            'cancelada' => Tono::Peligro,
            default => Tono::Neutro,
        };
    }
}
