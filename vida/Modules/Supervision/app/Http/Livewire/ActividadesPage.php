<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla de actividades grupales para el módulo de Supervisión.
 *
 * Programación y seguimiento de actividades grupales del centro:
 * cursos, talleres, grupos de apoyo. El supervisor crea la actividad
 * y gestiona su ciclo de vida; los profesionales gestionan la inscripción
 * de ciudadanos desde el módulo de Intervención.
 */
#[Layout('layouts.supervision')]
class ActividadesPage extends Component
{
    /**
     * Renderiza la pantalla de actividades grupales.
     */
    public function render(): View
    {
        return view('supervision::livewire.actividades-page');
    }
}
