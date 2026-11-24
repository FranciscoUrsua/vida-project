<?php

namespace Modules\Supervision\Http\Livewire;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Organizacion\Services\ConfiguracionService;

/**
 * Pantalla de auditoría de accesos para el módulo de Supervisión.
 *
 * Muestra los registros de auditoría relativos a ciudadanos cuya historia social
 * pertenece a la UO del supervisor (o su subtree). Permite filtrar por rango de
 * fechas, por ciudadanos de colectivos protegidos y por accesos sin autorización
 * explícita (accion = acceso_restringido), además de por profesional y por
 * ciudadano, y ordenar por las cabeceras de columna.
 *
 * Los nombres de los ciudadanos están cifrados en la BD: el filtro y la
 * ordenación por ciudadano se resuelven descifrando en PHP los nombres de los
 * ciudadanos del ámbito y pasando a SQL solo la lista de ids resultante, de modo
 * que la paginación sigue haciéndose en la BD.
 *
 * @property string $fechaDesde
 * @property string $fechaHasta
 * @property bool $soloProtegidos
 * @property bool $soloSinAutorizacion
 * @property string $profesionalId
 * @property string $ciudadano
 * @property string $ordenarPor
 * @property string $direccion
 * @property-read LengthAwarePaginator<int, Audit> $accesos
 * @property-read array<int, string> $profesionales
 */
#[Layout('layouts.supervision')]
class AuditoriaPage extends Component
{
    use WithPagination;

    /** @var string Fecha de inicio del rango de filtro (por defecto: 30 días atrás) */
    public string $fechaDesde = '';

    /** @var string Fecha de fin del rango de filtro */
    public string $fechaHasta = '';

    /** @var bool Filtrar solo accesos a ciudadanos de colectivos protegidos */
    public bool $soloProtegidos = false;

    /** @var bool Filtrar solo accesos sin autorización (accion = acceso_restringido) */
    public bool $soloSinAutorizacion = false;

    /** @var string Usuario (users.id) cuyos accesos se muestran; vacío = todos */
    public string $profesionalId = '';

    /** @var string Texto buscado en el nombre del ciudadano; vacío = todos */
    public string $ciudadano = '';

    /** @var string Columna de ordenación: profesional | ciudadano | fecha | accion | motivo */
    public string $ordenarPor = 'fecha';

    /** @var string Dirección de ordenación: asc | desc */
    public string $direccion = 'desc';

    /** @var list<string> Columnas por las que se puede ordenar */
    private const COLUMNAS_ORDENABLES = ['profesional', 'ciudadano', 'fecha', 'accion', 'motivo'];

    /** @var array<int, string>|null Nombres descifrados del ámbito, memorizados durante la petición */
    private ?array $nombresCiudadanos = null;

    /**
     * Inicializa las fechas del filtro por defecto (últimos 30 días).
     *
     * @return void
     */
    public function mount(): void
    {
        $this->fechaHasta = now()->toDateString();
        $this->fechaDesde = now()->subDays(30)->toDateString();
    }

    /**
     * Resetea la paginación cuando cambia cualquier filtro.
     *
     * @return void
     */
    public function updatedFechaDesde(): void
    {
        $this->resetPage();
    }

    /** @return void */
    public function updatedFechaHasta(): void
    {
        $this->resetPage();
    }

    /** @return void */
    public function updatedSoloProtegidos(): void
    {
        $this->resetPage();
    }

    /** @return void */
    public function updatedSoloSinAutorizacion(): void
    {
        $this->resetPage();
    }

    /** @return void */
    public function updatedProfesionalId(): void
    {
        $this->resetPage();
    }

    /** @return void */
    public function updatedCiudadano(): void
    {
        $this->resetPage();
    }

    /**
     * Ordena por la columna indicada; si ya era la columna activa, invierte la dirección.
     *
     * La fecha empieza en descendente (lo más reciente primero); el resto, en ascendente.
     * Columnas fuera de la lista blanca se ignoran.
     *
     * @param string $columna profesional | ciudadano | fecha | accion | motivo
     *
     * @return void
     */
    public function ordenar(string $columna): void
    {
        if (! in_array($columna, self::COLUMNAS_ORDENABLES, true)) {
            return;
        }

        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion = $columna === 'fecha' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    /**
     * Indica si el centro tiene colectivos protegidos configurados.
     * Condiciona la visibilidad de columnas y filtros.
     *
     * @return bool
     */
    #[Computed]
    public function tieneColectivosProtegidos(): bool
    {
        return (bool) app(ConfiguracionService::class)->get('tiene_colectivos_protegidos', false);
    }

    /**
     * Registros de auditoría de accesos en el ámbito de la UO del supervisor,
     * filtrados, ordenados y paginados a 50 por página.
     *
     * Filtra por ciudadanos cuya historia social pertenece a la UO del supervisor.
     * No usa AccesosExpedienteQuery (scoped a un ciudadano concreto) sino una
     * subquery directa sobre historias_sociales.
     *
     * @return LengthAwarePaginator<int, Audit>
     */
    #[Computed]
    public function accesos(): LengthAwarePaginator
    {
        $uoIds = auth()->user()->uoSubtreeIds();

        if (empty($uoIds)) {
            return Audit::whereNull('id')->paginate(50);
        }

        $query = $this->accesosDelAmbito($uoIds)
            ->select('audits.*')
            ->when(
                filled($this->fechaDesde),
                fn ($q) => $q->whereDate('audits.created_at', '>=', $this->fechaDesde)
            )
            ->when(
                filled($this->fechaHasta),
                fn ($q) => $q->whereDate('audits.created_at', '<=', $this->fechaHasta)
            )
            ->when(
                $this->soloProtegidos,
                fn ($q) => $q->whereIn('audits.ciudadano_id', function ($sub) use ($uoIds): void {
                    $sub->select('ciudadano_id')
                        ->from('historias_sociales')
                        ->whereIn('unidad_organizativa_id', $uoIds)
                        ->where('ciudadano_protegido', true);
                })
            )
            ->when(
                $this->soloSinAutorizacion,
                fn ($q) => $q->where('audits.accion', AccionAuditEnum::AccesoRestringido->value)
            )
            ->when(
                ctype_digit($this->profesionalId),
                fn ($q) => $q->where('audits.user_id', (int) $this->profesionalId)
            )
            ->when(
                trim($this->ciudadano) !== '',
                fn ($q) => $q->whereIn('audits.ciudadano_id', $this->ciudadanosQueCoinciden($uoIds))
            );

        $this->aplicarOrden($query, $uoIds);

        return $query
            ->with(['user.profesional', 'ciudadano'])
            ->paginate(50);
    }

    /**
     * Profesionales que han accedido a algún ciudadano del ámbito, para el filtro.
     *
     * Se toma todo el histórico, no solo el rango de fechas, para que el
     * profesional elegido no desaparezca del desplegable al cambiar las fechas.
     *
     * @return array<int, string> users.id → nombre del profesional (o email si no tiene ficha)
     */
    #[Computed]
    public function profesionales(): array
    {
        $uoIds = auth()->user()->uoSubtreeIds();

        if (empty($uoIds)) {
            return [];
        }

        $userIds = $this->accesosDelAmbito($uoIds)
            ->whereNotNull('audits.user_id')
            ->distinct()
            ->pluck('audits.user_id');

        return User::with('profesional')
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(fn ($user) => [
                $user->id => $user->profesional?->nombre_completo ?: $user->email,
            ])
            ->sort(fn (string $a, string $b) => strcmp(self::normalizar($a), self::normalizar($b)))
            ->all();
    }

    /**
     * Consulta base: registros de auditoría de ciudadanos con historia en las UO dadas.
     *
     * @param list<int> $uoIds
     *
     * @return Builder<Audit>
     */
    private function accesosDelAmbito(array $uoIds): Builder
    {
        return Audit::query()
            ->whereNotNull('audits.ciudadano_id')
            ->whereIn('audits.ciudadano_id', fn (QueryBuilder $sub) => $sub
                ->select('ciudadano_id')
                ->from('historias_sociales')
                ->whereIn('unidad_organizativa_id', $uoIds));
    }

    /**
     * Nombres descifrados de los ciudadanos del ámbito, ordenados alfabéticamente.
     *
     * Base del filtro y de la ordenación por ciudadano: el nombre está cifrado
     * en la BD y no puede compararse ni ordenarse en SQL. Se carga con las
     * reglas de visibilidad del modelo (AmbitoUoScope), las mismas que la
     * relación `ciudadano` de cada fila.
     *
     * @param list<int> $uoIds
     *
     * @return array<int, string> ciudadanos.id → nombre completo
     */
    private function nombresCiudadanosDelAmbito(array $uoIds): array
    {
        return $this->nombresCiudadanos ??= Ciudadano::query()
            ->whereIn('id', fn (QueryBuilder $sub) => $sub
                ->select('ciudadano_id')
                ->from('historias_sociales')
                ->whereIn('unidad_organizativa_id', $uoIds))
            ->get(['id', 'nombre', 'apellido1', 'apellido2'])
            ->mapWithKeys(fn (Ciudadano $c) => [$c->id => $c->nombre_completo])
            ->sort(fn (string $a, string $b) => strcmp(self::normalizar($a), self::normalizar($b)))
            ->all();
    }

    /**
     * Ids de los ciudadanos del ámbito cuyo nombre contiene el texto buscado,
     * sin distinguir mayúsculas ni tildes.
     *
     * @param list<int> $uoIds
     *
     * @return list<int>
     */
    private function ciudadanosQueCoinciden(array $uoIds): array
    {
        $buscado = self::normalizar($this->ciudadano);

        return array_keys(array_filter(
            $this->nombresCiudadanosDelAmbito($uoIds),
            fn (string $nombre) => str_contains(self::normalizar($nombre), $buscado)
        ));
    }

    /**
     * Aplica a la consulta el orden elegido en la cabecera, con la fecha
     * descendente como desempate.
     *
     * @param Builder<Audit> $query
     * @param list<int> $uoIds
     *
     * @return void
     */
    private function aplicarOrden(Builder $query, array $uoIds): void
    {
        $dir = $this->direccion === 'asc' ? 'asc' : 'desc';

        match ($this->ordenarPor) {
            'profesional' => $query
                ->leftJoin('users as u_orden', 'u_orden.id', '=', 'audits.user_id')
                ->leftJoin('profesionales as p_orden', 'p_orden.id', '=', 'u_orden.profesional_id')
                // Mismo texto que se pinta en la columna: nombre del profesional o, si no tiene ficha, el email.
                ->orderByRaw(
                    "LOWER(COALESCE(NULLIF(CONCAT_WS(' ', p_orden.nombre, p_orden.apellido1, p_orden.apellido2), ''), u_orden.email)) {$dir} NULLS LAST"
                ),
            'ciudadano' => $this->ordenarPorCiudadano($query, $uoIds, $dir),
            'accion' => $this->ordenarPorAccion($query, $dir),
            'motivo' => $query->orderByRaw("audits.contexto->>'motivo' {$dir} NULLS LAST"),
            default => $query->orderBy('audits.created_at', $dir),
        };

        $query->orderByDesc('audits.created_at')->orderByDesc('audits.id');
    }

    /**
     * Ordena por el nombre (descifrado) del ciudadano.
     *
     * Se pasa a PostgreSQL la lista de ids ya ordenada por nombre y se ordena
     * por la posición de cada fila en ella (`array_position`).
     *
     * @param Builder<Audit> $query
     * @param list<int> $uoIds
     * @param string $dir asc | desc
     *
     * @return void
     */
    private function ordenarPorCiudadano(Builder $query, array $uoIds, string $dir): void
    {
        $ids = array_keys($this->nombresCiudadanosDelAmbito($uoIds));

        if ($ids === []) {
            return;
        }

        $query->orderByRaw(
            "array_position(?::bigint[], audits.ciudadano_id) {$dir} NULLS LAST",
            ['{'.implode(',', $ids).'}']
        );
    }

    /**
     * Ordena por la etiqueta visible de la acción, no por su valor interno.
     *
     * @param Builder<Audit> $query
     * @param string $dir asc | desc
     *
     * @return void
     */
    private function ordenarPorAccion(Builder $query, string $dir): void
    {
        $casos = [];
        $bindings = [];

        foreach (AccionAuditEnum::cases() as $accion) {
            $casos[] = 'WHEN ? THEN ?';
            $bindings[] = $accion->value;
            $bindings[] = $accion->etiqueta();
        }

        $query->orderByRaw('CASE audits.accion '.implode(' ', $casos)." END {$dir}", $bindings);
    }

    /**
     * Normaliza un texto para comparar y ordenar sin mayúsculas ni tildes.
     *
     * @param string $texto
     *
     * @return string
     */
    private static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }

    /**
     * Mapa de ciudadano_id → ciudadano_protegido para los accesos de la página actual.
     *
     * Se resuelve con una sola query sobre historias_sociales, evitando
     * N+1 y la necesidad de añadir la relación historiaSocial al modelo Ciudadano.
     *
     * @return array<int, bool>
     */
    #[Computed]
    public function protegidosPorCiudadano(): array
    {
        $ids = $this->accesos->pluck('ciudadano_id')->filter()->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        return HistoriaSocial::whereIn('ciudadano_id', $ids)
            ->pluck('ciudadano_protegido', 'ciudadano_id')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }

    /**
     * Renderiza la pantalla de auditoría de accesos.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.auditoria-page');
    }
}
