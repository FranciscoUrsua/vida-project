<?php

namespace Modules\Intervencion\Http\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Intervencion\Services\IntervencionSidebarDataService;

/**
 * Sidebar del interfaz operativo de Intervención.
 *
 * Muestra la navegación principal y badges de conteo.
 * Se actualiza automáticamente cada 5 minutos mediante wire:poll.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega1.md §2
 */
class Sidebar extends Component
{
    /**
     * Contadores para los badges del sidebar.
     *
     * @return array{alertas: int, mensajes: int, notificaciones: int, casos: int}
     */
    #[Computed]
    public function datos(): array
    {
        return app(IntervencionSidebarDataService::class)->getData();
    }

    /**
     * @return \Illuminate\View\View
     */
    public function render(): \Illuminate\View\View
    {
        return view('intervencion::livewire.sidebar');
    }
}
