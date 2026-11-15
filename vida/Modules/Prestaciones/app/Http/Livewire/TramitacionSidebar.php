<?php

namespace Modules\Prestaciones\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Menú lateral de la superficie de tramitación (fase 3 bis, paso 5.3), con el número de
 * solicitudes pendientes de tomar en las unidades del usuario.
 */
class TramitacionSidebar extends Component
{
    /**
     * Solicitudes en bandeja de las unidades del usuario.
     *
     * @return int
     */
    #[Computed]
    public function pendientes(): int
    {
        return SolicitudPrestacion::where('estado', EstadoSolicitudPrestacion::EnBandeja->value)
            ->whereIn('unidad_tramitadora_id', Auth::user()->uoSubtreeIds())
            ->count();
    }

    /**
     * Pinta el menú.
     *
     * @return View
     */
    public function render(): View
    {
        return view('prestaciones::livewire.tramitacion-sidebar');
    }
}
