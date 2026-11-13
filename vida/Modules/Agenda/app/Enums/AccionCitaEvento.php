<?php

namespace Modules\Agenda\Enums;

/**
 * Acciones que quedan en el historial inmutable de solicitudes y citas
 * (docs/modulo-citas.md §2.4).
 */
enum AccionCitaEvento: string
{
    case SolicitudCreada = 'solicitud_creada';
    case SolicitudTomada = 'solicitud_tomada';
    case SolicitudSoltada = 'solicitud_soltada';
    case SolicitudDesistida = 'solicitud_desistida';
    case SolicitudAnulada = 'solicitud_anulada';
    case CitaCreada = 'cita_creada';
    case CitaReprogramada = 'cita_reprogramada';
    case CitaCancelada = 'cita_cancelada';
    case CitaReasignada = 'cita_reasignada';
    case Incomparecencia = 'incomparecencia';
    case CitaCompletada = 'cita_completada';
    case AcompanantesRegistrados = 'acompanantes_registrados';
    case CambioSolicitado = 'cambio_solicitado';
    case MarcadaPendienteCierre = 'marcada_pendiente_cierre';
    case NotificacionExternaEnviada = 'notificacion_externa_enviada';
    case CiudadanoIdentificado = 'ciudadano_identificado';

    /**
     * Etiqueta para la interfaz.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::SolicitudCreada => 'Solicitud creada',
            self::SolicitudTomada => 'Solicitud tomada',
            self::SolicitudSoltada => 'Solicitud soltada',
            self::SolicitudDesistida => 'Solicitud desistida',
            self::SolicitudAnulada => 'Solicitud anulada',
            self::CitaCreada => 'Cita creada',
            self::CitaReprogramada => 'Cita reprogramada',
            self::CitaCancelada => 'Cita cancelada',
            self::CitaReasignada => 'Cita reasignada',
            self::Incomparecencia => 'Incomparecencia',
            self::CitaCompletada => 'Cita completada',
            self::AcompanantesRegistrados => 'Acompañantes registrados',
            self::CambioSolicitado => 'Cambio solicitado',
            self::MarcadaPendienteCierre => 'Pendiente de cierre',
            self::NotificacionExternaEnviada => 'Notificación a cita previa',
            self::CiudadanoIdentificado => 'Persona identificada',
        };
    }
}
