<?php

namespace Modules\Intervencion\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeParticipante;

/**
 * Servicio de datos para el sidebar del interfaz operativo de Intervención.
 *
 * Proporciona los contadores para los badges del sidebar:
 * - Total de alertas directas pendientes de reconocimiento
 * - Total de mensajes no leídos
 * - Número de ciudadanos con plan activo asignados al profesional
 */
class IntervencionSidebarDataService
{
    /**
     * Número de alertas directas al usuario autenticado pendientes de reconocimiento.
     *
     * Solo cuenta alertas con destinatario_type = usuario. Las alertas por rol+UO
     * se tratan en la bandeja completa de alertas.
     */
    public function totalAlertas(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return Alerta::where('estado', EstadoAlerta::Pendiente)
            ->where('destinatario_type', DestinatarioType::Usuario)
            ->where('destinatario_usuario_id', Auth::id())
            ->count();
    }

    /**
     * Número de mensajes no leídos del usuario autenticado.
     */
    public function mensajesNoLeidos(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return MensajeParticipante::where('usuario_id', Auth::id())
            ->whereNull('archivado_en')
            ->get()
            ->sum(fn (MensajeParticipante $p) => $p->mensajesNoLeidos());
    }

    /**
     * Total de notificaciones (alertas + mensajes no leídos).
     * Usado para el badge del ítem "Alertas y mensajes".
     */
    public function totalNotificaciones(): int
    {
        return $this->totalAlertas() + $this->mensajesNoLeidos();
    }

    /**
     * Número de historias sociales con plan activo asignadas al profesional.
     * Usado para el badge del ítem "Mis casos".
     */
    public function misCasosCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return PlanDeIntervencion::where('profesional_responsable_id', Auth::id())
            ->where('estado', 'activo')
            ->distinct('historia_id')
            ->count('historia_id');
    }

    /**
     * Devuelve todos los datos del sidebar en un array.
     *
     * @return array{alertas: int, mensajes: int, notificaciones: int, casos: int}
     */
    public function getData(): array
    {
        return [
            'alertas' => $this->totalAlertas(),
            'mensajes' => $this->mensajesNoLeidos(),
            'notificaciones' => $this->totalNotificaciones(),
            'casos' => $this->misCasosCount(),
        ];
    }
}
