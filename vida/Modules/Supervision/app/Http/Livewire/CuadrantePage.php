<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla de cuadrante del centro para el módulo de Supervisión.
 *
 * Vista de gestión de la agenda del centro en su conjunto: disponibilidad
 * de todos los profesionales, introducción de excepciones (ausencias, bloqueos)
 * y gestión de reasignaciones de citas.
 * Consume el módulo Agenda; no duplica su lógica.
 */
#[Layout('layouts.supervision')]
class CuadrantePage extends Component
{
    /**
     * Renderiza la pantalla de cuadrante del centro.
     */
    public function render(): View
    {
        return view('supervision::livewire.cuadrante-page');
    }
}
