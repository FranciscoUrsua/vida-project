<?php

namespace Modules\Supervision\Http\Livewire;

use App\Enums\AccionAuditEnum;
use App\Models\Audit;
use Illuminate\Pagination\LengthAwarePaginator;
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
 * explícita (accion = acceso_restringido).
 *
 * @property string $fechaDesde
 * @property string $fechaHasta
 * @property bool $soloProtegidos
 * @property bool $soloSinAutorizacion
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
    public function updatedFechaDesde(): void { $this->resetPage(); }

    /** @return void */
    public function updatedFechaHasta(): void { $this->resetPage(); }

    /** @return void */
    public function updatedSoloProtegidos(): void { $this->resetPage(); }

    /** @return void */
    public function updatedSoloSinAutorizacion(): void { $this->resetPage(); }

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
     * paginados a 50 por página.
     *
     * Filtra por ciudadanos cuya historia social pertenece a la UO del supervisor.
     * No usa AccesosExpedienteQuery (scoped a un ciudadano concreto) sino una
     * subquery directa sobre historias_sociales.
     *
     * @return LengthAwarePaginator
     */
    #[Computed]
    public function accesos(): LengthAwarePaginator
    {
        $uoIds = auth()->user()?->uoSubtreeIds() ?? [];

        if (empty($uoIds)) {
            return Audit::whereNull('id')->paginate(50);
        }

        return Audit::query()
            ->whereNotNull('ciudadano_id')
            ->whereIn('ciudadano_id', function ($sub) use ($uoIds): void {
                $sub->select('ciudadano_id')
                    ->from('historias_sociales')
                    ->whereIn('unidad_organizativa_id', $uoIds);
            })
            ->when(
                filled($this->fechaDesde),
                fn ($q) => $q->whereDate('created_at', '>=', $this->fechaDesde)
            )
            ->when(
                filled($this->fechaHasta),
                fn ($q) => $q->whereDate('created_at', '<=', $this->fechaHasta)
            )
            ->when(
                $this->soloProtegidos,
                fn ($q) => $q->whereIn('ciudadano_id', function ($sub) use ($uoIds): void {
                    $sub->select('ciudadano_id')
                        ->from('historias_sociales')
                        ->whereIn('unidad_organizativa_id', $uoIds)
                        ->where('ciudadano_protegido', true);
                })
            )
            ->when(
                $this->soloSinAutorizacion,
                fn ($q) => $q->where('accion', AccionAuditEnum::AccesoRestringido->value)
            )
            ->with(['user.profesional', 'ciudadano'])
            ->orderByDesc('created_at')
            ->paginate(50);
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

        return \App\Models\HistoriaSocial::whereIn('ciudadano_id', $ids)
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
