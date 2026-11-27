<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Livewire\Concerns\OrdenaPorColumnas;
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
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Queries\MisCasosQuery;

/**
 * Pantalla "Mis casos" del interfaz operativo de Intervención.
 *
 * Listado único de las Historias Sociales que lleva el profesional autenticado:
 * aquellas de las que es profesional de referencia y aquellas con un plan en curso
 * del que es responsable (p. ej. el PIA en el CIAM), cada una una sola vez. La columna
 * «Planes» muestra todos los planes en curso de la persona por su tipo de plan
 * (PISO, PIA, plan psicológico…), de cualquier centro. Filtros por seguimiento y por
 * tipo de plan.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega2.md §3
 */
#[Layout('layouts.operativo')]
class MisCasosPage extends Component
{
    use OrdenaPorColumnas;
    use WithPagination;

    /** Valor del filtro de planes para las historias sin ningún plan en curso. */
    public const SIN_PLAN = 'sin';

    /** @var string Búsqueda textual por nombre/apellido; se resuelve en memoria (datos cifrados) */
    public string $busqueda = '';

    /** @var string Filtro por estado de seguimiento: '' | 'vencido' | 'proximo' | 'programado' | 'sin' */
    public string $filtroSeguimiento = '';

    /** @var string Filtro por planes: '' (todos) | 'sin' (ningún plan en curso) | id de un tipo de plan */
    public string $filtroPlanes = '';

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
     * Resetea la paginación al cambiar el filtro de planes.
     *
     * @return void
     */
    public function updatedFiltroPlanes(): void
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
     * Columnas ordenables de la tabla. 'seg' ordena por urgencia del seguimiento,
     * no por fecha simple; 'ciudadano' se ordena en memoria (nombre cifrado).
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function columnasOrdenables(): array
    {
        return ['ciudadano' => 'asc', 'historia' => 'asc', 'seg' => 'asc', 'inicio' => 'asc'];
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

    // -------------------------------------------------------------------------
    // Opciones del filtro de planes
    // -------------------------------------------------------------------------

    /**
     * Tipos de plan activos para el filtro «Planes», el principal (PISO) primero.
     *
     * @return Collection<int, string> Nombre del tipo de plan por id.
     */
    #[Computed]
    public function tiposPlan(): Collection
    {
        return TipoPlan::query()
            ->where('activo', true)
            ->orderByDesc('es_principal')
            ->orderBy('nombre')
            ->pluck('nombre', 'id');
    }

    // -------------------------------------------------------------------------
    // Consulta principal
    // -------------------------------------------------------------------------

    /**
     * Lista paginada de los casos del profesional autenticado, una fila por Historia Social.
     *
     * Una historia es del profesional si es su referencia (asignación vigente) o si es
     * responsable de alguno de sus planes en curso. «Inicio» es la fecha más antigua de
     * las dos vías. El próximo seguimiento es el más cercano de *sus* planes: los que
     * tiene como responsable y, si es la referencia, el plan principal; los planes de
     * otros profesionales salen en «Planes» pero no marcan su urgencia.
     *
     * @return LengthAwarePaginator<int, \stdClass>
     */
    #[Computed]
    public function casos(): LengthAwarePaginator
    {
        $hoy = today();
        $usuarioId = (int) Auth::id();

        // Seguimiento más reciente por plan
        $ultimoSeguimiento = DB::table('seguimientos_plan')
            ->select(DB::raw('DISTINCT ON (plan_id) plan_id, fecha_siguiente_seguimiento'))
            ->orderBy('plan_id')
            ->orderByDesc('created_at');

        // Próximo seguimiento por historia, solo de los planes que lleva el profesional
        $seguimientoPorHistoria = DB::table('planes_intervencion as p')
            ->joinSub($ultimoSeguimiento, 's', 's.plan_id', '=', 'p.id')
            ->leftJoin('tipos_plan as tp', 'tp.id', '=', 'p.tipo_plan_id')
            ->where('p.estado', '!=', EstadoPlan::Cerrado->value)
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($usuarioId): void {
                $q->where('p.profesional_responsable_id', $usuarioId)
                    ->orWhere(fn ($q2) => $q2->where('tp.es_principal', true)
                        ->whereIn('p.historia_id', MisCasosQuery::referencias($usuarioId)->select('historia_id')));
            })
            ->selectRaw('p.historia_id, MIN(s.fecha_siguiente_seguimiento) AS fecha_siguiente_seguimiento')
            ->groupBy('p.historia_id');

        $query = MisCasosQuery::historias($usuarioId)
            ->leftJoinSub($seguimientoPorHistoria, 'seg', 'seg.historia_id', '=', 'mh.historia_id')
            ->addSelect('seg.fecha_siguiente_seguimiento');

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

        // Filtro de planes: sobre todos los planes en curso de la persona, sean de quien sean
        $planesEnCurso = fn ($q) => $q->select(DB::raw(1))
            ->from('planes_intervencion as pf')
            ->whereColumn('pf.historia_id', 'mh.historia_id')
            ->where('pf.estado', '!=', EstadoPlan::Cerrado->value)
            ->whereNull('pf.deleted_at');

        if ($this->filtroPlanes === self::SIN_PLAN) {
            $query->whereNotExists($planesEnCurso);
        } elseif (ctype_digit($this->filtroPlanes)) {
            $tipoPlanId = (int) $this->filtroPlanes;
            $query->whereExists(fn ($q) => $planesEnCurso($q)->where('pf.tipo_plan_id', $tipoPlanId));
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
                'inicio' => $query->orderBy('mh.fecha_inicio', $this->direccion),
                default => $query->orderBy('mh.historia_id', $this->direccion),
            };

            // Desempate estable para la paginación
            $query->orderBy('mh.historia_id');
        }

        // Path en memoria: búsqueda activa o sort por nombre (ambos requieren desencriptar)
        if ($this->busqueda !== '' || $this->ordenarPor === 'ciudadano') {
            $allItems = $query->get();
            $usuario = Auth::user();

            $ciudadanos = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->whereIn('id', $allItems->pluck('ciudadano_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            // Se busca y ordena por el nombre que se muestra: una persona protegida
            // que el usuario no puede ver no se encuentra por su nombre real.
            $nombre = fn ($caso): string => PresentacionPersona::paraListado($ciudadanos->get($caso->ciudadano_id), $usuario)['nombre'];

            if ($this->busqueda !== '') {
                $term = mb_strtolower($this->busqueda);
                $allItems = $allItems->filter(fn ($caso) => str_contains(mb_strtolower($nombre($caso)), $term))->values();
            }

            if ($this->ordenarPor === 'ciudadano') {
                $clave = fn ($caso) => Str::lower(Str::ascii($nombre($caso)));
                $allItems = $allItems->sortBy($clave, SORT_REGULAR, $this->direccion === 'desc')->values();
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
     * Planes en curso de cada historia de la página, de cualquier centro y responsable,
     * el principal primero y los demás por fecha de inicio.
     *
     * @return Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, PlanDeIntervencion>> Planes por id de historia.
     */
    #[Computed]
    public function planesDelPage(): Collection
    {
        $ids = $this->filasDeLaPagina()->pluck('historia_id')->unique()->values();

        return PlanDeIntervencion::withoutGlobalScopes()
            ->with(['tipoPlan', 'centro'])
            ->whereIn('historia_id', $ids)
            ->where('estado', '!=', EstadoPlan::Cerrado->value)
            ->get()
            ->sortBy([
                fn (PlanDeIntervencion $a, PlanDeIntervencion $b) => self::esPrincipal($b) <=> self::esPrincipal($a),
                fn (PlanDeIntervencion $a, PlanDeIntervencion $b) => [$a->fecha_inicio, $a->id] <=> [$b->fecha_inicio, $b->id],
            ])
            ->groupBy('historia_id');
    }

    /**
     * Filas de la página actual del listado.
     *
     * @return Collection<int, \stdClass>
     */
    private function filasDeLaPagina(): Collection
    {
        return collect($this->casos->items());
    }

    /**
     * Si el plan es del tipo principal (el PISO), para ponerlo el primero en «Planes».
     *
     * @param PlanDeIntervencion $plan
     *
     * @return bool
     */
    private static function esPrincipal(PlanDeIntervencion $plan): bool
    {
        return $plan->tipoPlan !== null && $plan->tipoPlan->es_principal;
    }

    /**
     * Persona de cada caso de la página, ya preparada para el listado: una persona
     * protegida que el usuario no puede ver sale sin nombre ni enlace a la ficha.
     * Carga los ciudadanos en una sola consulta para evitar N+1 sobre datos cifrados.
     *
     * @return array<int, array{visible: bool, id: int|null, nombre: string, url: string|null, ciudadano: Ciudadano|null}> Por id de ciudadano.
     */
    #[Computed]
    public function personasDelPage(): array
    {
        $ids = $this->filasDeLaPagina()->pluck('ciudadano_id')->filter()->unique()->values();
        $usuario = Auth::user();

        $personas = [];
        foreach (Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->whereIn('id', $ids)->get() as $ciudadano) {
            $personas[$ciudadano->id] = PresentacionPersona::paraListado($ciudadano, $usuario);
        }

        return $personas;
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
