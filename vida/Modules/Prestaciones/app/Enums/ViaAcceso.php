<?php

namespace Modules\Prestaciones\Enums;

/**
 * Vía por la que una persona accede a una prestación (fase 3, F3-5).
 *
 * Es enum porque el código decide con ella (principio 3.10): una prestación de
 * `derivacion` crea un plan especializado del tipo `tipo_plan_derivacion_id` al
 * aceptarse en el centro de destino (`DerivacionService`). La fase 3 bis añade el
 * resto de vías (directa, tramitación interna, expediente externo).
 */
enum ViaAcceso: string
{
    case Plaza = 'plaza';
    case Derivacion = 'derivacion';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Plaza => 'Asignación de plaza',
            self::Derivacion => 'Derivación a atención especializada',
        };
    }
}
