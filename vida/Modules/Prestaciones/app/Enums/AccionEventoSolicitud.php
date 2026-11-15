<?php

namespace Modules\Prestaciones\Enums;

/**
 * Acciones que registra el historial de una solicitud de prestación (RN-11).
 *
 * Es enum porque el código decide con ella: una incidencia está abierta mientras no hay un
 * evento `incidencia_atendida` que la referencie, y la idempotencia de las actualizaciones
 * compara eventos de la misma acción.
 */
enum AccionEventoSolicitud: string
{
    case Creada = 'creada';
    case ABandeja = 'a_bandeja';
    case Tomada = 'tomada';
    case Soltada = 'soltada';
    case Devuelta = 'devuelta';
    case DocumentoVinculado = 'documento_vinculado';
    case DocumentoDesvinculado = 'documento_desvinculado';
    case Enviada = 'enviada';
    case EstadoActualizado = 'estado_actualizado';
    case Incidencia = 'incidencia';
    case IncidenciaAtendida = 'incidencia_atendida';
    case Resolucion = 'resolucion';
    case CerradaSinResolucion = 'cerrada_sin_resolucion';
    case Anulada = 'anulada';

    /**
     * Etiqueta para el historial.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Creada => 'Creada',
            self::ABandeja => 'Pasa a la bandeja',
            self::Tomada => 'Tomada',
            self::Soltada => 'Soltada',
            self::Devuelta => 'Devuelta al profesional',
            self::DocumentoVinculado => 'Documento vinculado',
            self::DocumentoDesvinculado => 'Documento desvinculado',
            self::Enviada => 'Enviada',
            self::EstadoActualizado => 'Estado del expediente',
            self::Incidencia => 'Incidencia',
            self::IncidenciaAtendida => 'Incidencia atendida',
            self::Resolucion => 'Resolución',
            self::CerradaSinResolucion => 'Cerrada sin resolución',
            self::Anulada => 'Anulada',
        };
    }
}
