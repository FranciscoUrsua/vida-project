<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Organizacion\Models\Configuracion;
use Modules\Organizacion\Services\ConfiguracionService;
use Modules\Supervision\Services\SupervisionSidebarDataService;

/**
 * Sidebar del interfaz operativo de Supervisión.
 *
 * Muestra la navegación principal con badge de aprobaciones pendientes.
 * Ítems condicionales (Plazas) según configuración del centro.
 * Se actualiza automáticamente cada 5 minutos mediante wire:poll.
 *
 * @property int $aprobacionesPendientes
 * @property bool $tienePlazas
 */
class Sidebar extends Component
{
    /**
     * Número de aprobaciones pendientes en el ámbito del supervisor.
     *
     * @return int
     */
    #[Computed]
    public function aprobacionesPendientes(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return app(SupervisionSidebarDataService::class)
            ->aprobacionesPendientes(auth()->id());
    }

    /**
     * Indica si el centro tiene plazas configuradas.
     * Determina la visibilidad del ítem «Plazas» en el sidebar.
     *
     * @return bool
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Datos de identidad visual configurados en el backoffice.
     *
     * @return array{logoUrl: string|null, nombreAplicacion: string|null}
     */
    #[Computed]
    public function branding(): array
    {
        return [
            'logoUrl'         => Configuracion::logoUrl(),
            'nombreAplicacion' => Configuracion::nombreAplicacion(),
        ];
    }

    /**
     * Renderiza el sidebar del módulo.
     */
    public function render(): View
    {
        return view('supervision::livewire.sidebar');
    }
}
