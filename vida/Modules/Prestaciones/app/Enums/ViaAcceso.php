<?php

namespace Modules\Prestaciones\Enums;

/**
 * Vía por la que una persona accede a una prestación (RN-01 de docs/modulo-solicitudes-expedientes.md).
 *
 * Es enum porque el código decide con ella (principio 3.10): «Solicitar prestación» sigue un
 * camino distinto por vía (registro directo, prescripción de plaza, derivación al plan
 * especializado o solicitud con tramitación). `tipo_prestacion` ya no decide el flujo.
 */
enum ViaAcceso: string
{
    case Directa = 'directa';
    case Plaza = 'plaza';
    case TramitacionInterna = 'tramitacion_interna';
    case ExpedienteExterno = 'expediente_externo';
    case Derivacion = 'derivacion';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Directa => 'Directa',
            self::Plaza => 'Asignación de plaza',
            self::TramitacionInterna => 'Tramitación interna',
            self::ExpedienteExterno => 'Expediente externo',
            self::Derivacion => 'Derivación a atención especializada',
        };
    }

    /**
     * Si la vía crea una solicitud que tramita una unidad (RN-02).
     *
     * @return bool
     */
    public function esTramitacion(): bool
    {
        return $this === self::TramitacionInterna || $this === self::ExpedienteExterno;
    }
}
