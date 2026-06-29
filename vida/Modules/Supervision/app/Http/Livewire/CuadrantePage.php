<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Modules\Centro\Models\Centro;

/**
 * Pantalla de cuadrante del centro para el módulo de Supervisión.
 *
 * Redirige al CuadranteMesComponent del módulo Agenda para el mes en curso,
 * resolviendo el centro del supervisor desde su UO activa.
 */
#[Layout('layouts.supervision')]
class CuadrantePage extends Component
{
    /**
     * Resuelve el centro del supervisor y redirige al cuadrante del mes actual.
     *
     * @return RedirectResponse|Redirector|void
     */
    public function mount(): mixed
    {
        $uoId  = auth()->user()?->uosActivas()->first()?->id;
        $centro = $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;

        if ($centro === null) {
            return null;
        }

        return $this->redirect(
            route('agenda.cuadrante', [
                'centro' => $centro->id,
                'anyo'   => now()->year,
                'mes'    => now()->month,
            ]),
            navigate: true
        );
    }

    /**
     * Renderiza la vista de espera mientras se resuelve la redirección.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.cuadrante-page');
    }
}
