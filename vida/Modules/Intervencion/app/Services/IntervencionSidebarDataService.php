<?php

namespace Modules\Intervencion\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Intervencion\Queries\MisCasosQuery;
use Modules\Mensajes\Services\ContadoresBandejaService;

/**
 * Contadores de «Mi trabajo» en el menú operativo (App\Livewire\MenuOperativo).
 *
 * Proporciona:
 * - Alertas, avisos y mensajes pendientes (los mismos que la bandeja)
 * - Número de casos del profesional: los mismos que el listado «Mis casos» (MisCasosQuery)
 */
class IntervencionSidebarDataService
{
    /**
     * Número de historias sociales del listado «Mis casos»: de las que es referencia
     * o responsable de un plan en curso. Usado para el badge del ítem "Mis casos".
     *
     * @return int
     */
    public function misCasosCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return MisCasosQuery::historias((int) Auth::id())->count();
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
