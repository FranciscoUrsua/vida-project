<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Livewire\Concerns\OrdenaPorColumnas;
use App\Models\CatalogoSistema;
use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Services\PresentacionPersona;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Intervencion\Models\PlanDeIntervencion;

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
    use OrdenaPorColumnas;
    use WithPagination;

    /** @var string Búsqueda textual por nombre/apellido; se resuelve en memoria (datos cifrados) */
    public string $busqueda = '';

    /** @var string Filtro por estado de seguimiento: '' | 'vencido' | 'proximo' | 'programado' | 'sin' */
    public string $filtroSeguimiento = '';

    /** @var string Filtro por estado del plan ASP: '' | 'activo' | 'revision' | 'sin' */
    public string $filtroPiso = '';

    /** @var string Filtro por derivación especializada: '' | 'con' | 'sin' */
    public string $filtroEsp = '';

    /** @var string Columna de ordenación de «Otros planes»: persona | estado | seguimiento */
    public string $ordenOtros = 'seguimiento';

    /** @var string Dirección de ordenación de «Otros planes»: asc | desc */
    public string $direccionOtros = 'asc';

    /** @var int Número de resultados por página */
    public int $porPagina = 10;

    // -------------------------------------------------------------------------
    // Hooks de actualización de propiedades
    // -------------------------------------------------------------------------

    /**
     * Resetea la paginación al cambiar el filtro de seguimiento.
     */
    public function updatedFiltroSeguimiento(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el filtro de plan ASP.
     */
    public function updatedFiltroPiso(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el filtro de derivación especializada.
     */
    public function updatedFiltroEsp(): void
    {
        $this->resetPage();
    }

    /**
     * Resetea la paginación al cambiar el texto de búsqueda.
     */
    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * Columnas ordenables de la tabla principal. 'seg' ordena por urgencia del
     * seguimiento, no por fecha simple; 'ciudadano' se ordena en memoria (nombre cifrado).
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function columnasOrdenables(): array
    {
        return ['ciudadano' => 'asc', 'historia' => 'asc', 'seg' => 'asc', 'inicio' => 'asc', 'esp' => 'asc'];
    }

    /**
     * Orden inicial: los seguimientos más urgentes primero.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function ordenInicial(): array
    {
        return ['seg', 'asc'];
    }

    /**
     * Ordena la tabla «Otros planes»; si la columna ya era la activa, invierte la dirección.
     *
     * @param string $columna persona | estado | seguimiento
     *
     * @return void
     */
    public function ordenarOtros(string $columna): void
    {
        if (! in_array($columna, ['persona', 'estado', 'seguimiento'], true)) {
            return;
        }

        if ($this->ordenOtros === $columna) {
            $this->direccionOtros = $this->direccionOtros === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenOtros = $columna;
            $this->direccionOtros = 'asc';
        }
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
     * El origen es la tabla asignaciones_profesional (vigentes). Los planes
     * generales ASP y los seguimientos se incorporan como LEFT JOIN para
     * mostrar información adicional; su ausencia no excluye el caso.
     */
    #[Computed]
    public function casos(): LengthAwarePaginator
    {
        $hoy = today();

        // Subconsulta: seguimiento más reciente por plan
        $subSeguimiento = DB::table('seguimientos_plan')
            ->select(DB::raw('DISTINCT ON (plan_id) plan_id, fecha_siguiente_seguimiento'))
            ->orderBy('plan_id')
            ->orderByDesc('created_at');

        // Subconsulta: plan principal (el PISO) más relevante por historia (activo > en_revision > borrador).
        // El plan principal lo decide su tipo de plan, no `tipo` (fase 3, F3-1).
        $subPlan = DB::table('planes_intervencion as pp')
            ->select(DB::raw('DISTINCT ON (pp.historia_id) pp.historia_id, pp.id as plan_id, pp.estado as plan_estado'))
            ->join('tipos_plan as tpp', 'tpp.id', '=', 'pp.tipo_plan_id')
            ->where('tpp.es_principal', true)
            ->where('pp.estado', '!=', 'cerrado')
            ->whereNull('pp.deleted_at')
            ->orderBy('pp.historia_id')
            ->orderByRaw("CASE pp.estado WHEN 'activo' THEN 0 WHEN 'en_revision' THEN 1 ELSE 2 END")
            ->orderByDesc('pp.created_at');

        // Subconsulta: nº de planes especializados activos por historia
        $subEsp = DB::table('planes_intervencion as pe')
            ->selectRaw('pe.historia_id, COUNT(*) as planes_esp_count')
            ->where('pe.tipo', 'especializado')
            ->where('pe.estado', 'activo')
            ->whereNull('pe.deleted_at')
            ->groupBy('pe.historia_id');

        $query = DB::table('asignaciones_profesional as ap')
            ->join('historias_sociales as hs', 'hs.id', '=', 'ap.historia_id')
            ->leftJoinSub($subPlan, 'pi', 'pi.historia_id', '=', 'ap.historia_id')
            ->leftJoinSub($subSeguimiento, 'seg', 'seg.plan_id', '=', 'pi.plan_id')
            ->leftJoinSub($subEsp, 'esp', 'esp.historia_id', '=', 'hs.id')
            ->where('ap.profesional_id', Auth::id())
            ->whereNull('ap.fecha_fin')
            ->whereNull('ap.deleted_at')
            ->whereNull('hs.deleted_at')
            ->select([
                'ap.historia_id',
                'ap.fecha_inicio',
                'hs.ciudadano_id',
                'pi.plan_id',
                'pi.plan_estado',
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

        // Filtro de plan ASP
        if ($this->filtroPiso === 'activo') {
            $query->where('pi.plan_estado', 'activo');
        } elseif ($this->filtroPiso === 'revision') {
            $query->where('pi.plan_estado', 'en_revision');
        } elseif ($this->filtroPiso === 'sin') {
            $query->whereNull('pi.plan_id');
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
                'inicio' => $query->orderBy('ap.fecha_inicio', $this->direccion),
                'esp' => $query->orderByRaw("COALESCE(esp.planes_esp_count, 0) {$dir}, ap.fecha_inicio ASC"),
                'historia' => $query->orderBy('ap.historia_id', $this->direccion),
                default => $query->orderBy('ap.historia_id', $this->direccion),
            };
        }

        // Path en memoria: búsqueda activa o sort por nombre (ambos requieren desencriptar)
        if ($this->busqueda !== '' || $this->ordenarPor === 'ciudadano') {
            $allItems = $query->get();

            $ciudadanoIds = $allItems->pluck('ciudadano_id')->filter()->unique();
            $ciudadanos = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->whereIn('id', $ciudadanoIds)
                ->get()
                ->keyBy('id');

            if ($this->busqueda !== '') {
                $term = mb_strtolower($this->busqueda);
                $allItems = $allItems->filter(
                    fn ($caso) => str_contains(
                        mb_strtolower($ciudadanos->get($caso->ciudadano_id)->nombre_completo ?? ''),
                        $term
                    )
                )->values();
            }

            if ($this->ordenarPor === 'ciudadano') {
                $allItems = $this->direccion === 'desc'
                    ? $allItems->sortByDesc(fn ($caso) => $ciudadanos->get($caso->ciudadano_id)->nombre_completo ?? "\xFF")
                    : $allItems->sortBy(fn ($caso) => $ciudadanos->get($caso->ciudadano_id)->nombre_completo ?? "\xFF");
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
     * Planes no principales en curso de los que el profesional es responsable (fase 3, A3.6).
     *
     * Planes de ASP como el psicológico o especializados como el PIA: no salen de la
     * referencia (la tabla de casos) sino de la responsabilidad del plan. Cada uno se
     * identifica por su tipo de plan, nunca como el PISO.
     *
     * @return Collection<int, array{id: int, persona: string, tipo: string, centro: string, estado: string, proximo_seguimiento: string|null}>
     */
    #[Computed]
    public function otrosPlanes(): Collection
    {
        $usuario = Auth::user();

        $filas = PlanDeIntervencion::withoutGlobalScopes()
            ->with(['tipoPlan', 'centro', 'historia' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('profesional_responsable_id', $usuario->id)
            ->vigentes()
            ->whereNotIn('id', PlanDeIntervencion::withoutGlobalScopes()->select('id')->principal())
            ->orderBy('fecha_inicio')
            ->get()
            ->map(function (PlanDeIntervencion $plan) use ($usuario): array {
                $persona = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($plan->historia?->ciudadano_id);

                return [
                    'id' => $plan->id,
                    'persona' => PresentacionPersona::paraListado($persona, $usuario)['nombre'],
                    'tipo' => $plan->tipoPlan->nombre ?? 'Plan',
                    'centro' => $plan->centro->nombre_corto ?? $plan->centro->nombre ?? '—',
                    'estado' => $plan->estado->label(),
                    'proximo_seguimiento' => $plan->seguimientos()->latest()->value('fecha_siguiente_seguimiento'),
                ];
            });

        // Se ordena en memoria: el nombre de la persona está cifrado y la lista es corta.
        // Sin fecha de seguimiento, al final en ambos sentidos.
        $clave = match ($this->ordenOtros) {
            'persona' => fn (array $fila) => Str::lower(Str::ascii($fila['persona'])),
            'estado' => fn (array $fila) => Str::lower(Str::ascii($fila['estado'])),
            default => fn (array $fila) => $fila['proximo_seguimiento'] ?? ($this->direccionOtros === 'asc' ? '9999-12-31' : '0000-01-01'),
        };

        return $filas->sortBy($clave, SORT_REGULAR, $this->direccionOtros === 'desc')->values();
    }

    /**
     * Renderiza la bandeja de casos del profesional.
     */
    public function render(): View
    {
        return view('intervencion::livewire.mis-casos-page');
    }
}
