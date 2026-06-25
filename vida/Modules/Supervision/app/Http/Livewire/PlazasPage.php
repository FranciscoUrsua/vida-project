<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla de gestión de plazas para el módulo de Supervisión.
 *
 * Gestión del inventario de plazas del centro: el supervisor decide
 * qué plazas existen y cuáles están operativas. Solo visible en centros
 * con tiene_plazas=true en la configuración.
 */
#[Layout('layouts.supervision')]
class PlazasPage extends Component
{
    /**
     * Renderiza la pantalla de plazas del centro.
     */
    public function render(): View
    {
        return view('supervision::livewire.plazas-page');
    }
}
