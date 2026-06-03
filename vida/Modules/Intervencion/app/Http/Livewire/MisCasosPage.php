<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\CatalogoSistema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla "Mis casos" del interfaz operativo de Intervención.
 *
 * Muestra la lista paginada de ciudadanos con plan general activo
 * asignados al profesional autenticado. Incluye filtros por estado
 * de seguimiento, plan ASP y derivaciones especializadas.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §3
 */
#[Layout('layouts.operativo')]
class MisCasosPage extends Component
{
    use WithPagination;

    /** @var string Búsqueda textual (no activa aún — datos cifrados) */
    public string $busqueda = '';

    /** @var string Filtro por estado de seguimiento: '' | 'vencido' | 'proximo' | 'programado' | 'sin' */
    public string $filtroSeguimiento = '';

    /** @var string Filtro por estado del plan ASP: '' | 'activo' | 'revision' | 'sin' */
    public string $filtroPiso = '';

    /** @var string Filtro por derivación especializada: '' | 'con' | 'sin' */
    public string $filtroEsp = '';

    /** @var string Campo de ordenación: 'seg' | 'nombre' */
    public string $ordenarPor = 'seg';

    /** @var int Número de resultados por página */
    public int $porPagina = 10;

    // -------------------------------------------------------------------------
    // Hooks de actualización de propiedades
    // -------------------------------------------------------------------------

    public function updatedFiltroSeguimiento(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroPiso(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEsp(): void
    {
        $this->resetPage();
    }

    public function updatedOrdenarPor(): void
    {
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // Nombre configurable del plan ASP
    // -------------------------------------------------------------------------

    /**
     * Etiqueta configurable del tipo de plan general (PISO o nombre alternativo).
     * Se lee del catálogo de sistema para permitir cambio sin deploy.
     */
    public function nombrePlanAsp(): string
    {
        return CatalogoSistema::valor('nombre_plan_asp', 'PISO');
    }

    // -------------------------------------------------------------------------
    // Consulta principal
    // -------------------------------------------------------------------------

    /**
     * Lista paginada de casos asignados al profesional autenticado.
     *
     * Cada fila contiene los datos del plan general ASP activo y el
     * siguiente seguimiento programado (o null si no existe).
     */
    #[Computed]
    public function casos(): LengthAwarePaginator
    {
        $hoy = today();

        // Subconsulta: fecha del seguimiento más reciente por plan
        $subSeguimiento = DB::table('seguimientos_plan')
            ->select(DB::raw('DISTINCT ON (plan_id) plan_id, fecha_siguiente_seguimiento'))
            ->orderBy('plan_id')
            ->orderByDesc('created_at');

        // Subconsulta: nº de planes especializados activos por historia
        $subEsp = DB::table('planes_intervencion as pe')
            ->selectRaw('pe.historia_id, COUNT(*) as planes_esp_count')
            ->where('pe.tipo', 'especializado')
            ->where('pe.estado', 'activo')
            ->whereNull('pe.deleted_at')
            ->groupBy('pe.historia_id');

        $query = DB::table('planes_intervencion as pi')
            ->join('historias_sociales as hs', 'hs.id', '=', 'pi.historia_id')
            ->leftJoinSub($subSeguimiento, 'seg', 'seg.plan_id', '=', 'pi.id')
            ->leftJoinSub($subEsp, 'esp', 'esp.historia_id', '=', 'hs.id')
            ->where('pi.profesional_responsable_id', Auth::id())
            ->where('pi.tipo', 'general_asp')
            ->where('pi.estado', 'activo')
            ->whereNull('hs.deleted_at')
            ->whereNull('pi.deleted_at')
            ->select([
                'pi.id as plan_id',
                'pi.historia_id',
                'pi.fecha_inicio',
                'hs.ciudadano_id',
                'seg.fecha_siguiente_seguimiento',
                DB::raw('COALESCE(esp.planes_esp_count, 0) as planes_esp_count'),
            ]);

        // Filtro de seguimiento
        if ($this->filtroSeguimiento === 'vencido') {
            $query->where('seg.fecha_siguiente_seguimiento', '<', $hoy);
        } elseif ($this->filtroSeguimiento === 'proximo') {
            $query->whereBetween('seg.fecha_siguiente_seguimiento', [$hoy, $hoy->copy()->addDays(7)]);
        } elseif ($this->filtroSeguimiento === 'programado') {
            $query->where('seg.fecha_siguiente_seguimiento', '>', $hoy->copy()->addDays(7));
        } elseif ($this->filtroSeguimiento === 'sin') {
            $query->whereNull('seg.fecha_siguiente_seguimiento');
        }

        // Filtro de planes especializados
        if ($this->filtroEsp === 'con') {
            $query->where(DB::raw('COALESCE(esp.planes_esp_count, 0)'), '>', 0);
        } elseif ($this->filtroEsp === 'sin') {
            $query->where(DB::raw('COALESCE(esp.planes_esp_count, 0)'), '=', 0);
        }

        // Orden por seguimiento (vencido → próximo → programado → sin) o por nombre
        if ($this->ordenarPor === 'seg') {
            $query->orderByRaw('
                CASE
                    WHEN seg.fecha_siguiente_seguimiento < CURRENT_DATE THEN 0
                    WHEN seg.fecha_siguiente_seguimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + 7 THEN 1
                    WHEN seg.fecha_siguiente_seguimiento > CURRENT_DATE + 7 THEN 2
                    ELSE 3
                END ASC,
                seg.fecha_siguiente_seguimiento ASC NULLS LAST
            ');
        } else {
            $query->orderBy('pi.historia_id');
        }

        return $query->paginate($this->porPagina);
    }

    public function render(): View
    {
        return view('intervencion::livewire.mis-casos-page');
    }
}
