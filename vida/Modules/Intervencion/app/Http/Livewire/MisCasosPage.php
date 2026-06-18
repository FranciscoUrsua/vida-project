<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    /** @var string Búsqueda textual por nombre/apellido; se resuelve en memoria (datos cifrados) */
    public string $busqueda = '';

    /** @var string Filtro por estado de seguimiento: '' | 'vencido' | 'proximo' | 'programado' | 'sin' */
    public string $filtroSeguimiento = '';

    /** @var string Filtro por estado del plan ASP: '' | 'activo' | 'revision' | 'sin' */
    public string $filtroPiso = '';

    /** @var string Filtro por derivación especializada: '' | 'con' | 'sin' */
    public string $filtroEsp = '';

    /**
     * Campo de ordenación activo.
     * Valores: 'seg' | 'inicio' | 'esp'
     * ('seg' usa ordenación semántica por urgencia, no cronológica simple)
     */
    public string $ordenarPor = 'seg';

    /** @var string Dirección de ordenación: 'asc' | 'desc' */
    public string $direccion = 'asc';

    /** @var int Número de resultados por página */
    public int $porPagina = 10;

    // -------------------------------------------------------------------------
    // Hooks de actualización de propiedades
    // -------------------------------------------------------------------------

    /**
     * Resetea la paginación al cambiar el filtro de seguimiento.
     *
     * @return void
     */
    public function updatedFiltroSeguimiento(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el filtro de plan ASP.
     *
     * @return void
     */
    public function updatedFiltroPiso(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el filtro de derivación especializada.
     *
     * @return void
     */
    public function updatedFiltroEsp(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el criterio de ordenación.
     *
     * @return void
     */
    public function updatedOrdenarPor(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el texto de búsqueda.
     *
     * @return void
     */
    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * Cambia la columna de ordenación o alterna la dirección si ya está activa.
     * Valores de campo permitidos: 'ciudadano', 'historia', 'seg', 'inicio', 'esp'.
     * 'ciudadano' aplica ordenación en memoria (nombre cifrado, no ordenable en DB).
     *
     * @param string $campo Identificador de columna.
     *
     * @return void
     */
    public function sortBy(string $campo): void
    {
        if (! in_array($campo, ['ciudadano', 'historia', 'seg', 'inicio', 'esp'], true)) {
            return;
        }

        if ($this->ordenarPor === $campo) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $campo;
            $this->direccion = 'asc';
        }

        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // Nombre configurable del plan ASP
    // -------------------------------------------------------------------------

    /**
     * Etiqueta configurable del tipo de plan general (PISO o nombre alternativo).
     * Se lee del catálogo de sistema para permitir cambio sin deploy.
     *
     * @return string
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
     *
     * @return LengthAwarePaginator
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

        // Ordenación DB para todos los campos excepto nombre (cifrado, se ordena en memoria)
        if ($this->ordenarPor !== 'ciudadano') {
            $dir = $this->direccion === 'desc' ? 'DESC' : 'ASC';

            match ($this->ordenarPor) {
                // Semáforo de urgencia: vencido → próximo → programado → sin (invertible)
                'seg' => $query->orderByRaw("
                    CASE
                        WHEN seg.fecha_siguiente_seguimiento < CURRENT_DATE THEN 0
                        WHEN seg.fecha_siguiente_seguimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + 7 THEN 1
                        WHEN seg.fecha_siguiente_seguimiento > CURRENT_DATE + 7 THEN 2
                        ELSE 3
                    END {$dir},
                    seg.fecha_siguiente_seguimiento {$dir} NULLS LAST
                "),
                'inicio'   => $query->orderBy('pi.fecha_inicio', $this->direccion),
                'esp'      => $query->orderByRaw("COALESCE(esp.planes_esp_count, 0) {$dir}, pi.fecha_inicio ASC"),
                'historia' => $query->orderBy('pi.historia_id', $this->direccion),
                default    => $query->orderBy('pi.historia_id', $this->direccion),
            };
        }

        // Path en memoria: búsqueda activa o sort por nombre (ambos requieren desencriptar)
        if ($this->busqueda !== '' || $this->ordenarPor === 'ciudadano') {
            $allItems = $query->get();

            $ciudadanoIds = $allItems->pluck('ciudadano_id')->filter()->unique();
            $ciudadanos   = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->whereIn('id', $ciudadanoIds)
                ->get()
                ->keyBy('id');

            if ($this->busqueda !== '') {
                $term     = mb_strtolower($this->busqueda);
                $allItems = $allItems->filter(
                    fn ($caso) => str_contains(
                        mb_strtolower($ciudadanos->get($caso->ciudadano_id)?->nombre_completo ?? ''),
                        $term
                    )
                )->values();
            }

            if ($this->ordenarPor === 'ciudadano') {
                $allItems = $this->direccion === 'desc'
                    ? $allItems->sortByDesc(fn ($caso) => $ciudadanos->get($caso->ciudadano_id)?->nombre_completo ?? "\xFF")
                    : $allItems->sortBy(fn ($caso) => $ciudadanos->get($caso->ciudadano_id)?->nombre_completo ?? "\xFF");
                $allItems = $allItems->values();
            }

            $page = $this->getPage();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $allItems->forPage($page, $this->porPagina)->all(),
                $allItems->count(),
                $this->porPagina,
                $page,
                ['path' => request()->url(), 'pageName' => 'page'],
            );
        }

        return $query->paginate($this->porPagina);
    }

    /**
     * Mapa de ciudadanos correspondientes a la página actual de casos.
     * Carga los nombres en una sola consulta para evitar N+1 sobre datos cifrados.
     *
     * @return Collection<int, Ciudadano>
     */
    #[Computed]
    public function ciudadanosDelPage(): Collection
    {
        $ids = collect($this->casos->items())->pluck('ciudadano_id')->filter()->unique()->values();

        return Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Renderiza la bandeja de casos del profesional.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.mis-casos-page');
    }
}
