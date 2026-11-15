<?php

namespace Modules\Prestaciones\Http\Livewire;

use App\Models\HistoriaSocial;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Models\SeguimientoPlan;
use Modules\Prestaciones\Enums\ViaAcceso;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Services\PrestacionPersonaService;
use Modules\Prestaciones\Services\SolicitudPrestacionService;

/**
 * «Solicitar prestación»: una sola puerta para todas las vías (RN-01, fase 3 bis paso 5.1).
 *
 * Se incrusta en la caja de herramientas de la Historia Social, en cada objetivo del plan y
 * tras registrar un seguimiento. Ofrece las prestaciones efectivas del centro del usuario y,
 * según la vía: directa → registro; plaza → prescripción (PrescribirRecursoModal); derivación →
 * modal de derivación del plan principal; tramitación → crea la solicitud y abre su pantalla.
 *
 * @property-read Collection<int, Prestacion> $prestaciones
 * @property-read Centro|null $centro
 */
class SolicitarPrestacion extends Component
{
    #[Locked]
    public int $historiaId;

    #[Locked]
    public ?int $planId = null;

    #[Locked]
    public ?int $objetivoId = null;

    #[Locked]
    public ?int $seguimientoId = null;

    /** Texto del botón que abre el selector. */
    #[Locked]
    public string $etiqueta = 'Solicitar prestación';

    /** Botón grande de la caja de herramientas de la Historia Social. */
    #[Locked]
    public bool $herramienta = false;

    public bool $abierto = false;

    public string $busqueda = '';

    /** Prestación directa pendiente de confirmar. */
    public ?int $directaId = null;

    public string $aviso = '';

    /** Enlace del aviso (el plan principal, para derivar). */
    public ?string $avisoUrl = null;

    /**
     * Centro activo del usuario (el del contexto).
     *
     * @return Centro|null
     */
    #[Computed]
    public function centro(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroActivo(Auth::user());
    }

    /**
     * Prestaciones efectivas del centro con vía de acceso, filtradas por la búsqueda.
     *
     * @return Collection<int, Prestacion>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        if ($this->centro === null) {
            return collect();
        }

        $termino = mb_strtolower(trim($this->busqueda));

        return app(ConfiguracionEfectivaCentro::class)->prestaciones($this->centro)
            ->filter(fn (Prestacion $p) => $p->via_acceso !== null)
            ->filter(fn (Prestacion $p) => $termino === '' || str_contains(mb_strtolower($p->nombre), $termino))
            ->values();
    }

    /**
     * Abre el selector.
     *
     * @return void
     */
    public function abrir(): void
    {
        $this->reset(['busqueda', 'directaId', 'aviso', 'avisoUrl']);
        $this->resetErrorBag();
        $this->abierto = true;
    }

    /**
     * Cierra el selector.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->abierto = false;
    }

    /**
     * Sigue el camino de la vía de la prestación elegida.
     *
     * @param int $prestacionId
     *
     * @return mixed
     */
    public function elegir(int $prestacionId): mixed
    {
        $prestacion = $this->prestaciones->firstWhere('id', $prestacionId);
        if ($prestacion === null) {
            $this->addError('prestacion', 'Esa prestación no se ofrece en tu centro.');

            return null;
        }

        return match ($prestacion->via_acceso) {
            ViaAcceso::Directa => $this->directaId = $prestacion->id,
            ViaAcceso::Plaza => $this->abrirPrescripcion($prestacion),
            ViaAcceso::Derivacion => $this->abrirDerivacion(),
            default => $this->crearSolicitud($prestacion),
        };
    }

    /**
     * Registra la prestación directa elegida (activa desde hoy).
     *
     * @return void
     */
    public function registrarDirecta(): void
    {
        $prestacion = $this->prestaciones->firstWhere('id', $this->directaId);
        if ($prestacion === null) {
            return;
        }

        try {
            app(PrestacionPersonaService::class)->registrarDirecta(
                $this->historia()->ciudadano()->withoutGlobalScopes()->firstOrFail(), $prestacion, Auth::user(), $this->centro, $this->plan(), $this->objetivo(),
            );
        } catch (AuthorizationException|DomainException $e) {
            $this->addError('prestacion', $e->getMessage());

            return;
        }

        $this->abierto = false;
        $this->directaId = null;
        $this->dispatch('prestaciones-cambiadas');
    }

    /**
     * Pinta el botón y el selector.
     *
     * @return View
     */
    public function render(): View
    {
        return view('prestaciones::livewire.solicitar-prestacion');
    }

    /**
     * Plaza: abre la prescripción con la prestación.
     *
     * @param Prestacion $prestacion
     *
     * @return null
     */
    private function abrirPrescripcion(Prestacion $prestacion): null
    {
        $this->abierto = false;
        $this->dispatch('abrir-prescribir-recurso', prestacionId: $prestacion->id);

        return null;
    }

    /**
     * Derivación: el modal del plan principal (desde el plan) o un aviso con el enlace (desde la ficha).
     *
     * @return null
     */
    private function abrirDerivacion(): null
    {
        $plan = $this->plan();

        if ($plan !== null && $plan->esPrincipal()) {
            $this->abierto = false;
            $this->dispatch('abrir-derivacion', objetivoId: $this->objetivoId);

            return null;
        }

        $principal = PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $this->historiaId)->principal()->where('estado', 'activo')->first();
        $this->aviso = $principal !== null
            ? 'La derivación se hace desde el plan principal.'
            : 'La derivación se hace desde el plan principal, y esta persona no tiene uno activo.';
        $this->avisoUrl = $principal !== null ? route('intervencion.plan.show', $principal) : null;

        return null;
    }

    /**
     * Tramitación: crea la solicitud en borrador y abre su pantalla.
     *
     * @param Prestacion $prestacion
     *
     * @return mixed
     */
    private function crearSolicitud(Prestacion $prestacion): mixed
    {
        try {
            $solicitud = app(SolicitudPrestacionService::class)->crear(
                $this->historia()->ciudadano()->withoutGlobalScopes()->firstOrFail(), $prestacion, Auth::user(),
                $this->plan(), $this->objetivo(), $this->centro, $this->seguimientoId ? SeguimientoPlan::find($this->seguimientoId) : null,
            );
        } catch (AuthorizationException|DomainException $e) {
            $this->addError('prestacion', $e->getMessage());

            return null;
        }

        return $this->redirect(route('prestaciones.solicitud', $solicitud), navigate: true);
    }

    /**
     * Historia del contexto (sin scope: la pantalla que incrusta el componente ya la ha autorizado).
     *
     * @return HistoriaSocial
     */
    private function historia(): HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
    }

    /**
     * Plan del contexto, si lo hay.
     *
     * @return PlanDeIntervencion|null
     */
    private function plan(): ?PlanDeIntervencion
    {
        return $this->planId ? PlanDeIntervencion::withoutGlobalScopes()->find($this->planId) : null;
    }

    /**
     * Objetivo del contexto, si lo hay.
     *
     * @return PlanObjetivo|null
     */
    private function objetivo(): ?PlanObjetivo
    {
        return $this->objetivoId ? PlanObjetivo::find($this->objetivoId) : null;
    }
}
