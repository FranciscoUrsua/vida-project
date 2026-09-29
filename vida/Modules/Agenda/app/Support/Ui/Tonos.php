<?php

namespace Modules\Agenda\Support\Ui;

use App\Support\Ui\Tono;

/**
 * Tono de color de los valores de la agenda del supervisor que no tienen enum
 * propio. Las clases salen de {@see Tono}.
 */
final class Tonos
{
    /**
     * Badge del tipo de evento interno.
     *
     * @param string $tipo sesion_interna|actividad_colectiva|coordinacion
     * @return Tono
     */
    public static function tipoEvento(string $tipo): Tono
    {
        return match ($tipo) {
            'sesion_interna' => Tono::Primario,
            'actividad_colectiva' => Tono::Exito,
            'coordinacion' => Tono::Aviso,
            default => Tono::Neutro,
        };
    }

    /**
     * Badge de una franja del cuadrante; `null` si el tipo no tiene color.
     *
     * @param string $tipo atencion|sesion|colectivo|reserva
     * @return Tono|null
     */
    public static function tipoFranja(string $tipo): ?Tono
    {
        return match ($tipo) {
            'atencion' => Tono::Primario,
            'sesion' => Tono::Info,
            'colectivo' => Tono::Exito,
            'reserva' => Tono::Neutro,
            default => null,
        };
    }
}
