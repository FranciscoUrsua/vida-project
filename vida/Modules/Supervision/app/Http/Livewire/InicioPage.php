<?php

namespace Modules\Supervision\Http\Livewire;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;
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
 *
 * @property-read float $ratioCarga
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Modules\Usuarios\Models\UsuarioRol> $aprobacionesPendientes
 * @property-read float $umbralRatio
 * @property-read \Illuminate\Support\Collection<int, array{nombre: string, inicio: string, fin: string, total: int, disponibles: int, reservados: int}> $cuadranteDeHoy
 */
#[Layout('layouts.supervision')]
class InicioPage extends Component
{
    /**
     * Umbral de ratio personas/profesional para mostrar advertencia visual.
     */
    #[Computed]
    public function umbralRatio(): float
    {
        return (float) app(ConfiguracionService::class)->get('umbral_ratio_carga', 5);
    }

    /**
     * Ratio actual personas/profesional en la UO del supervisor.
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
     * Resumen compacto del cuadrante de hoy: una fila por profesional con slots activos.
     *
     * @return SupportCollection<int, array{nombre: string, inicio: string, fin: string, total: int, disponibles: int, reservados: int}>
     */
    #[Computed]
    public function cuadranteDeHoy(): SupportCollection
    {
        $uoId   = $this->uoIdSupervisor();
        $centro = $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;

        if ($centro === null) {
            return collect();
        }

        $slots = Slot::where('centro_id', $centro->id)
            ->where('fecha', today()->toDateString())
            ->with(['usuario.profesional'])
            ->orderBy('hora_inicio')
            ->get();

        return $slots
            ->groupBy('usuario_id')
            ->map(function ($grupo) {
                $prof = $grupo->first()?->usuario?->profesional;

                return [
                    'nombre'      => $prof?->nombre_completo ?? $grupo->first()?->usuario?->email ?? '—',
                    'inicio'      => substr($grupo->min('hora_inicio'), 0, 5),
                    'fin'         => substr($grupo->max('hora_fin'), 0, 5),
                    'total'       => $grupo->count(),
                    'disponibles' => $grupo->filter(fn ($s) => $s->estado === EstadoSlot::Disponible)->count(),
                    'reservados'  => $grupo->filter(fn ($s) => $s->estado === EstadoSlot::Reservado)->count(),
                ];
            })
            ->sortBy('nombre')
            ->values();
    }

    /**
     * Solicitudes pendientes de aprobación en el ámbito del supervisor (máximo 5).
     *
     * @return Collection<int, UsuarioRol>
     */
    #[Computed]
    public function aprobacionesPendientes(): Collection
    {
        $uoIds = auth()->user()->uoSubtreeIds();

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
     */
    #[Computed]
    public function totalAprobacionesPendientes(): int
    {
        return app(SupervisionSidebarDataService::class)
            ->aprobacionesPendientes(auth()->id());
    }

    /**
     * Indica si el ratio de carga supera el umbral configurado.
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
     */
    private function uoIdSupervisor(): ?int
    {
        return auth()->user()?->uosActivas()->first()?->id;
    }
}
