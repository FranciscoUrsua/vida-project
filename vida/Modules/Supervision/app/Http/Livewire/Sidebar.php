<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Centro\Models\Centro;
use Modules\Organizacion\Models\Configuracion;
use Modules\Organizacion\Services\ConfiguracionService;
use Modules\Supervision\Services\SupervisionSidebarDataService;

/**
 * Sidebar unificado del interfaz operativo de Supervisión y Agenda.
 *
 * Cubre toda la navegación del supervisor: páginas de Supervisión y
 * páginas de Agenda (cuadrante, ausencias, excepciones, eventos).
 * Badge de aprobaciones pendientes e ítems condicionales (Plazas) según
 * configuración del centro. Se actualiza cada 60 segundos.
 *
 * @property int $aprobacionesPendientes
 * @property int $citasPendientesBadge
 * @property bool $tienePlazas
 */
class Sidebar extends Component
{
    /**
     * Número de aprobaciones pendientes en el ámbito del supervisor.
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
     */
    #[Computed]
    public function tienePlazas(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_plazas', false);
    }

    /**
     * Citas canceladas por ausencia pendientes de gestionar hoy en el centro.
     *
     * @return int
     */
    #[Computed]
    public function citasPendientesBadge(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $uoId   = auth()->user()?->uosActivas()->first()?->id;
        $centro = $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;

        if ($centro === null) {
            return 0;
        }

        return Cita::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::Cancelada->value)
            ->where('motivo_cancelacion', 'like', '%Ausencia del profesional%')
            ->whereDoesntHave('reasignacion')
            ->count();
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
            'logoUrl' => Configuracion::logoUrl(),
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
