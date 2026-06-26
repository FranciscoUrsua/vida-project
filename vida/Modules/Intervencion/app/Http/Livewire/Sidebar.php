<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Intervencion\Services\IntervencionSidebarDataService;
use Modules\Organizacion\Models\Configuracion;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Sidebar del interfaz operativo de Intervención.
 *
 * Muestra la navegación principal y badges de conteo.
 * El ítem «Recursos» es condicional: solo visible si el centro tiene plazas configuradas.
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
     * Indica si el centro tiene plazas configuradas.
     * Determina la visibilidad del ítem «Recursos» en el sidebar.
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Datos de identidad visual configurados en el backoffice.
     * Se usa para el logotipo y nombre de aplicación en la cabecera del sidebar.
     *
     * @return array{logoUrl: string|null, nombreAplicacion: string|null}
     */
    #[Computed]
    public function branding(): array
    {
        return [
            'logoUrl' => Configuracion::logoUrl(),
            'nombreAplicacion' => Configuracion::nombreAplicacion(),
        ];
    }

    /**
     * Renderiza el sidebar del módulo.
     */
    public function render(): View
    {
        return view('intervencion::livewire.sidebar');
    }
}
