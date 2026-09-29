<?php

namespace Modules\Ciudadania\Support\Ui;

use App\Support\Ui\Tono;

/**
 * Tono de color de los valores de la ficha del ciudadano que no tienen enum
 * propio. Las clases salen de {@see Tono}.
 */
final class Tonos
{
    /**
     * Badge del nivel de identificación del ciudadano.
     *
     * @param string|null $nivel identificado|probable|no_identificado
     * @return Tono
     */
    public static function nivelIdentificacion(?string $nivel): Tono
    {
        return match ($nivel) {
            'identificado' => Tono::Exito,
            'probable' => Tono::Aviso,
            default => Tono::Peligro,
        };
    }

    /**
     * Badge de estado de una prestación del resumen.
     *
     * @param string $estado Estado de la prestación.
     * @return Tono
     */
    public static function estadoPrestacion(string $estado): Tono
    {
        return match ($estado) {
            'activo' => Tono::Exito,
            'en_tramite' => Tono::Aviso,
            'finalizado' => Tono::Neutro,
            default => Tono::Peligro,
        };
    }

    /**
     * Badge del tipo de registro de atención.
     *
     * @param string $tipo informacion|actividad|contacto
     * @return Tono
     */
    public static function tipoRegistroAtencion(string $tipo): Tono
    {
        return match ($tipo) {
            'informacion' => Tono::Primario,
            'actividad' => Tono::Exito,
            'contacto' => Tono::Aviso,
            default => Tono::Neutro,
        };
    }
}
