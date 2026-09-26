<?php

namespace Modules\Intervencion\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeParticipante;

/**
 * Servicio de datos para el sidebar del interfaz operativo de Intervención.
 *
 * Proporciona los contadores para los badges del sidebar:
 * - Total de alertas y avisos pendientes visibles para el usuario
 * - Total de mensajes no leídos
 * - Número de ciudadanos con plan activo asignados al profesional
 */
class IntervencionSidebarDataService
{
    /**
     * Número de alertas y avisos pendientes visibles para el usuario autenticado:
     * los directos y los dirigidos a su rol en su UO.
     *
     * @return int
     */
    public function totalAlertas(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return Alerta::pendientesPara(Auth::user())->count();
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
     * Número de historias sociales asignadas al profesional con asignación vigente.
     * Usado para el badge del ítem "Mis casos".
     */
    public function misCasosCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return AsignacionProfesional::where('profesional_id', Auth::id())
            ->whereNull('fecha_fin')
            ->whereNull('deleted_at')
            ->count();
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
