<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Audit;
use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use App\Models\UnidadOrganizativa;
use App\Queries\AccesosExpedienteQuery;
use App\Support\Auditoria\CambiosAuditoria;
use App\Support\Auditoria\ClasificacionAcceso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\PlanDeIntervencion;

/**
 * Drawer lateral de «Últimos accesos» de la Historia Social (U3, U3.2, U3.3): detalle de un
 * acceso (quién, cuándo, qué acción, sobre qué registro y los cambios campo a campo) y el
 * historial completo paginado con el filtro «Solo modificaciones».
 *
 * Respeta la visibilidad actual: todo pasa por `AccesosExpedienteQuery::paraUsuario`, también
 * cuando llega un id directamente, así que quien solo ve sus propios accesos solo abre los suyos.
 *
 * @property-read HistoriaSocial $historia
 * @property-read Audit|null $acceso
 * @property-read LengthAwarePaginator<int, Audit> $lista
 * @property-read list<array{campo: string, etiqueta: string, antes: string, despues: string, protegido: bool}> $cambios
 */
class DetalleAccesoDrawer extends Component
{
    use WithPagination;

    /** Accesos por página en «Ver todo». */
    private const POR_PAGINA = 20;

    /** @var int Historia Social cuyo expediente se consulta */
    #[Locked]
    public int $historiaId;

    /** @var bool Drawer abierto */
    public bool $abierto = false;

    /** @var string 'detalle' o 'lista' */
    public string $modo = 'detalle';

    /** @var bool El detalle se abrió desde la lista: muestra «← Volver a la lista» */
    public bool $desdeLista = false;

    /** @var int|null Registro de auditoría que se muestra */
    public ?int $auditId = null;

    /** @var bool Filtro «Solo modificaciones» de la lista */
    public bool $soloModificaciones = false;

    /**
     * Comprueba que el usuario ve la Historia Social.
     *
     * @param int $historiaId
     *
     * @return void
     */
    public function mount(int $historiaId): void
    {
        $this->historiaId = $historiaId;
        Gate::authorize('view', $this->historia);
    }

    /**
     * Historia Social consultada (sin scopes: la autoriza la policy).
     *
     * @return HistoriaSocial
     */
    #[Computed]
    public function historia(): HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
    }

    /**
     * Accesos visibles para el usuario (U3.2).
     *
     * @return Builder<Audit>
     */
    private function visibles(): Builder
    {
        $ciudadano = Ciudadano::withoutGlobalScopes()->findOrFail($this->historia->ciudadano_id);

        return app(AccesosExpedienteQuery::class)->paraUsuario(Auth::user(), $ciudadano, $this->historia);
    }

    /**
     * Abre el detalle de un acceso. Un acceso que el usuario no puede ver responde 403.
     *
     * @param int $id
     *
     * @return void
     */
    #[On('abrir-detalle-acceso')]
    public function abrirDetalle(int $id): void
    {
        abort_unless($this->visibles()->whereKey($id)->exists(), 403);

        $this->desdeLista = $this->abierto && $this->modo === 'lista';
        $this->auditId = $id;
        $this->modo = 'detalle';
        $this->abierto = true;
        unset($this->acceso, $this->cambios);
    }

    /**
     * Abre el historial completo de accesos visibles (U3.3).
     *
     * @return void
     */
    #[On('abrir-historial-accesos')]
    public function abrirLista(): void
    {
        $this->modo = 'lista';
        $this->auditId = null;
        $this->desdeLista = false;
        $this->abierto = true;
        $this->resetPage('accesos');
    }

    /**
     * Vuelve del detalle a la lista.
     *
     * @return void
     */
    public function volver(): void
    {
        $this->modo = 'lista';
        $this->auditId = null;
        $this->desdeLista = false;
    }

    /**
     * Cierra el drawer.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->abierto = false;
        $this->auditId = null;
        $this->desdeLista = false;
    }

    /**
     * Al cambiar el filtro, vuelve a la primera página.
     *
     * @return void
     */
    public function updatedSoloModificaciones(): void
    {
        $this->resetPage('accesos');
    }

    /**
     * Acceso que se muestra, comprobado otra vez contra la visibilidad del usuario.
     *
     * @return Audit|null
     */
    #[Computed]
    public function acceso(): ?Audit
    {
        if ($this->auditId === null) {
            return null;
        }

        return $this->visibles()->with('user.profesional')->whereKey($this->auditId)->first();
    }

    /**
     * Cambios campo a campo del acceso abierto.
     *
     * @return list<array{campo: string, etiqueta: string, antes: string, despues: string, protegido: bool}>
     */
    #[Computed]
    public function cambios(): array
    {
        return $this->acceso === null ? [] : CambiosAuditoria::de($this->acceso);
    }

    /**
     * Página del historial completo.
     *
     * @return LengthAwarePaginator<int, Audit>
     */
    #[Computed]
    public function lista(): LengthAwarePaginator
    {
        $query = $this->visibles();

        if ($this->soloModificaciones) {
            $query->whereIn('accion', ClasificacionAcceso::ACCIONES_CAMBIO);
        }

        return $query->paginate(self::POR_PAGINA, pageName: 'accesos');
    }

    /**
     * UO desde la que se hizo el acceso: la anotada en el contexto o la del profesional.
     *
     * @param Audit $acceso
     *
     * @return string|null
     */
    public function uoDe(Audit $acceso): ?string
    {
        $uoId = $acceso->contexto['unidad_organizativa_id'] ?? $acceso->user?->profesional?->unidad_organizativa_id;

        return $uoId === null ? null : UnidadOrganizativa::find($uoId)?->nombre;
    }

    /**
     * Nombre del registro afectado («Registro» en el detalle).
     *
     * @param Audit $acceso
     *
     * @return string
     */
    public function nombreRegistro(Audit $acceso): string
    {
        $modelo = ((array) __('auditoria.modelos'))[$acceso->auditable_type] ?? 'un registro';

        return mb_strtoupper(mb_substr($modelo, 0, 1)).mb_substr($modelo, 1);
    }

    /**
     * Enlace al registro afectado, si existe y el usuario lo puede ver.
     *
     * @param Audit $acceso
     *
     * @return string|null
     */
    public function enlaceRegistro(Audit $acceso): ?string
    {
        return match ($acceso->auditable_type) {
            PlanDeIntervencion::class => $this->enlacePlan($acceso->auditable_id),
            Ficha::class => $this->enlaceFicha($acceso->auditable_id),
            Apunte::class => $this->enlaceApunte($acceso->auditable_id),
            HistoriaSocial::class => $acceso->auditable_id === $this->historiaId ? route('intervencion.ciudadano.show', $this->historiaId) : null,
            default => null,
        };
    }

    /**
     * Enlace al plan, si el usuario lo puede ver.
     *
     * @param int|null $id
     *
     * @return string|null
     */
    private function enlacePlan(?int $id): ?string
    {
        $plan = $id === null ? null : PlanDeIntervencion::withoutGlobalScopes()->find($id);

        return $plan !== null && Gate::allows('view', $plan) ? route('intervencion.plan.show', $plan) : null;
    }

    /**
     * Enlace a la ficha de valoración de esta historia.
     *
     * @param int|null $id
     *
     * @return string|null
     */
    private function enlaceFicha(?int $id): ?string
    {
        $ficha = $id === null ? null : Ficha::find($id);

        return $ficha !== null && $ficha->historia_id === $this->historiaId
            ? route('intervencion.ficha.show', ['historia' => $this->historiaId, 'ficha' => $ficha->id])
            : null;
    }

    /**
     * Apunte de esta historia; los privados, solo para su autor.
     *
     * @param int|null $id
     *
     * @return string|null
     */
    private function enlaceApunte(?int $id): ?string
    {
        $apunte = $id === null ? null : Apunte::withoutGlobalScopes()->find($id);

        if ($apunte === null || $apunte->historia_id !== $this->historiaId) {
            return null;
        }

        if ($apunte->visibilidad === VisibilidadApunte::Privada && $apunte->autor_id !== Auth::id()) {
            return null;
        }

        return route('intervencion.ciudadano.show', ['historia' => $this->historiaId, 'apunte' => $apunte->id]);
    }

    /**
     * Pinta el drawer.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.detalle-acceso-drawer');
    }
}
