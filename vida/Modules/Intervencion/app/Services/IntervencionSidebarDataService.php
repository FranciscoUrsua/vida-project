<?php

namespace Modules\Intervencion\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Intervencion\Models\AsignacionProfesional;
use Modules\Mensajes\Services\ContadoresBandejaService;

/**
 * Servicio de datos para el sidebar del interfaz operativo de Intervención.
 *
 * Proporciona los contadores para los badges del sidebar:
 * - Alertas, avisos y mensajes pendientes (los mismos que la bandeja)
 * - Número de ciudadanos con plan activo asignados al profesional
 */
class IntervencionSidebarDataService
{
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
     * Contadores de los badges del sidebar: los de la bandeja (alertas, avisos
     * y mensajes, desde ContadoresBandejaService) y los casos asignados.
     *
     * @return array{alertas: int, avisos: int, mensajes: int, casos: int}
     */
    public function getData(): array
    {
        $bandeja = Auth::check()
            ? app(ContadoresBandejaService::class)->para(Auth::user())
            : ['alertas' => 0, 'avisos' => 0, 'mensajes' => 0];

        return $bandeja + ['casos' => $this->misCasosCount()];
    }
}
