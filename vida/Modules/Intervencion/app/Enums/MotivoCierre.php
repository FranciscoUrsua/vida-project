<?php

namespace Modules\Intervencion\Enums;

/**
 * Motivos normalizados para cerrar un Plan de Intervención.
 *
 * Refleja los motivos operativos reales según el diseño funcional del módulo.
 */
enum MotivoCierre: string
{
    case NegativaFirma            = 'negativa_firma';
    case ConsecucionObjetivos     = 'consecucion_objetivos';
    case CambioResidencia         = 'cambio_residencia';
    case ImposibilidadLocalizacion = 'imposibilidad_localizacion';
    case Fallecimiento            = 'fallecimiento';
    case FinIntervencion          = 'fin_intervencion';

    /**
     * Etiqueta legible para mostrar el motivo de cierre al profesional.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::NegativaFirma             => 'Negativa a la firma / falta de colaboración',
            self::ConsecucionObjetivos      => 'Consecución de objetivos',
            self::CambioResidencia          => 'Cambio de residencia',
            self::ImposibilidadLocalizacion => 'Imposibilidad de localizar a la familia',
            self::Fallecimiento             => 'Fallecimiento',
            self::FinIntervencion           => 'Finalización de la intervención',
        };
    }

    /**
     * Indica si este motivo requiere constancia explícita en el historial de apuntes.
     *
     * @return bool
     */
    public function requiereConstanciaHistorial(): bool
    {
        return in_array($this, [
            self::NegativaFirma,
            self::ImposibilidadLocalizacion,
        ]);
    }
}
