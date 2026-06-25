<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Organizacion\Services\ConfiguracionService;
use Modules\Supervision\Services\IndicadoresCentroService;
use Modules\Supervision\Services\SupervisionSidebarDataService;
use Modules\Usuarios\Models\UsuarioRol;

/**
 * Pantalla de inicio (dashboard) del módulo de Supervisión.
 *
 * Muestra los KPIs operativos del centro (ratio de carga, espera media,
 * profesionales sin agenda, actividades próximas) y las aprobaciones
 * pendientes más recientes. No es analítica histórica.
 */
#[Layout('layouts.supervision')]
class InicioPage extends Component
{
    /**
     * Umbral de ratio personas/profesional para mostrar advertencia visual.
     *
     * @return float
     */
    #[Computed]
    public function umbralRatio(): float
    {
        return (float) app(ConfiguracionService::class)->get('umbral_ratio_carga', 5);
    }

    /**
     * Ratio actual personas/profesional en la UO del supervisor.
     *
     * @return float
     */
    #[Computed]
    public function ratioCarga(): float
    {
        $uoId = $this->uoIdSupervisor();

        if ($uoId === null) {
            return 0.0;
        }

        return app(IndicadoresCentroService::class)->ratioCarga($uoId);
    }

    /**
     * Solicitudes pendientes de aprobación en el ámbito del supervisor (máximo 5).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, UsuarioRol>
     */
    #[Computed]
    public function aprobacionesPendientes(): \Illuminate\Database\Eloquent\Collection
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return collect();
        }

        return UsuarioRol::where('estado', 'pendiente_aprobacion')
            ->whereHas('usuario', function ($q) use ($uoIds) {
                $q->whereHas('adscripcionesVigentes', function ($q2) use ($uoIds) {
                    $q2->whereIn('unidad_organizativa_id', $uoIds);
                });
            })
            ->orderBy('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Total de aprobaciones pendientes para mostrar el enlace «Ver todas».
     *
     * @return int
     */
    #[Computed]
    public function totalAprobacionesPendientes(): int
    {
        return app(SupervisionSidebarDataService::class)
            ->aprobacionesPendientes(auth()->id());
    }

    /**
     * Indica si el ratio de carga supera el umbral configurado.
     *
     * @return bool
     */
    #[Computed]
    public function ratioSuperaUmbral(): bool
    {
        return $this->ratioCarga > $this->umbralRatio;
    }

    /**
     * Renderiza la pantalla de inicio.
     */
    public function render(): View
    {
        return view('supervision::livewire.inicio-page');
    }

    /**
     * ID de la primera UO activa del supervisor autenticado, o null si no tiene.
     *
     * @return int|null
     */
    private function uoIdSupervisor(): ?int
    {
        return auth()->user()?->uosActivas()->first()?->id;
    }
}
