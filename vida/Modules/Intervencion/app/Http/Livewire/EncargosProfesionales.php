<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\Ciudadano;
use App\Models\HistoriaSocial;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Models\DocumentoVinculo;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\AreaEncargo;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Intervencion\Services\EncargoService;

/**
 * Encargos profesionales de una persona y modal «Encargo profesional» (bloque B de
 * docs/instrucciones-cli/2026-11-encargo-profesional.md).
 *
 * Se incrusta en la Historia Social (herramienta «Encargo profesional»: todos los encargos
 * de la historia) y en el plan principal (los encargos hechos desde él, con objetivo
 * opcional). Encarga quien gestiona la historia o edita el plan principal activo, y puede
 * actuar en su centro (EP-2, RN-15); todas las reglas las aplica EncargoService, esta
 * pantalla solo las refleja.
 *
 * @property-read HistoriaSocial $historia
 * @property-read PlanDeIntervencion|null $plan
 * @property-read bool $puedeEncargar
 * @property-read Collection<int, Centro> $centrosDestino
 * @property-read Collection<int, AreaEncargo> $areas
 * @property-read Collection<int, PlanObjetivo> $objetivos
 * @property-read Collection<int, Documento> $documentosCarpeta
 * @property-read Collection<int, Derivacion> $encargos
 */
class EncargosProfesionales extends Component
{
    /** Historia Social de la persona. */
    #[Locked]
    public int $historiaId;

    /** Plan principal, si el componente está en el plan; null en la Historia Social. */
    #[Locked]
    public ?int $planId = null;

    public bool $modalAbierto = false;

    public ?int $centroDestinoId = null;

    /** Área del encargo (EA-3), que decide las herramientas del profesional. */
    public ?int $areaId = null;

    public ?int $objetivoId = null;

    public string $motivo = '';

    /** @var list<int> Documentos de la carpeta de la persona que se adjuntan. */
    public array $documentoIds = [];

    /** Encargo cuyo historial se despliega. */
    public ?int $historialDe = null;

    /** Encargo pendiente que se va a anular. */
    public ?int $anularId = null;

    public string $motivoAnulacion = '';

    /**
     * Historia Social (sin scope: la página que incrusta el componente ya ha autorizado la vista).
     *
     * @return HistoriaSocial
     */
    #[Computed]
    public function historia(): HistoriaSocial
    {
        return HistoriaSocial::withoutGlobalScopes()->findOrFail($this->historiaId);
    }

    /**
     * Plan principal desde el que se encarga, si lo hay.
     *
     * @return PlanDeIntervencion|null
     */
    #[Computed]
    public function plan(): ?PlanDeIntervencion
    {
        return $this->planId !== null
            ? PlanDeIntervencion::withoutGlobalScopes()->where('historia_id', $this->historiaId)->findOrFail($this->planId)
            : null;
    }

    /**
     * Si el usuario puede encargar desde aquí: desde el plan, principal, activo y editable;
     * desde la historia, abierta y gestionada por él. En los dos casos, RN-15 en su centro.
     *
     * @return bool
     */
    #[Computed]
    public function puedeEncargar(): bool
    {
        $usuario = Auth::user();

        if ($this->plan !== null) {
            return $this->plan->esPrincipal()
                && $this->plan->estado === EstadoPlan::Activo
                && $usuario->can('update', $this->plan)
                && app(CentroDeUsuario::class)->puedeActuar($usuario, $this->plan->centro);
        }

        return $this->historia->estado !== 'cerrada'
            && $usuario->can('update', $this->historia)
            && app(CentroDeUsuario::class)->puedeActuar($usuario, $this->historia->centro);
    }

    /**
     * Centros con algún profesional que recibe encargos.
     *
     * @return Collection<int, Centro>
     */
    #[Computed]
    public function centrosDestino(): Collection
    {
        return app(EncargoService::class)->centrosDestino();
    }

    /**
     * Áreas activas que se pueden elegir al encargar (EA-3).
     *
     * @return Collection<int, AreaEncargo>
     */
    #[Computed]
    public function areas(): Collection
    {
        return AreaEncargo::activas()->orderBy('nombre')->get();
    }

    /**
     * Objetivos del plan principal (solo en el plan).
     *
     * @return Collection<int, PlanObjetivo>
     */
    #[Computed]
    public function objetivos(): Collection
    {
        return $this->planId !== null
            ? PlanObjetivo::where('plan_id', $this->planId)->orderBy('orden')->get()
            : collect();
    }

    /**
     * Documentos disponibles de la carpeta de la persona que el usuario puede ver.
     *
     * @return Collection<int, Documento>
     */
    #[Computed]
    public function documentosCarpeta(): Collection
    {
        $ids = DocumentoVinculo::query()->activos()
            ->where('vinculable_type', (new Ciudadano)->getMorphClass())
            ->where('vinculable_id', $this->historia->ciudadano_id)
            ->pluck('documento_id');

        return Documento::whereKey($ids)->with('tipo')->latest()->get()
            ->filter(fn (Documento $documento) => $documento->estaDisponible() && Gate::allows('view', $documento))
            ->values()
            ->toBase();
    }

    /**
     * Encargos de la historia (o del plan, si está en el plan), los más recientes primero.
     *
     * @return Collection<int, Derivacion>
     */
    #[Computed]
    public function encargos(): Collection
    {
        return Derivacion::query()->encargos()
            ->where('historia_id', $this->historiaId)
            ->when($this->planId !== null, fn ($q) => $q->where('plan_origen_id', $this->planId))
            ->with(['centroDestino', 'area', 'objetivo', 'eventos.actor.profesional', 'profesionalResponsable.profesional', 'derivadaPor.profesional'])
            ->latest()
            ->get();
    }

    /**
     * Abre el modal del encargo, opcionalmente desde un objetivo (evento de PlanPage).
     *
     * @param int|null $objetivoId
     *
     * @return void
     */
    #[On('abrir-encargo')]
    public function abrir(?int $objetivoId = null): void
    {
        // R1: rechazo al abrir, con el mensaje de RN-15
        app(CentroDeUsuario::class)->exigirActuacion(Auth::user(), $this->plan->centro ?? $this->historia->centro);

        if (! $this->puedeEncargar) {
            return;
        }

        $this->reset(['centroDestinoId', 'areaId', 'motivo', 'documentoIds']);
        $this->resetErrorBag();
        $this->objetivoId = $this->planId !== null ? $objetivoId : null;
        $this->modalAbierto = true;
    }

    /**
     * Cierra el modal sin encargar.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->modalAbierto = false;
    }

    /**
     * Registra el encargo con EncargoService.
     *
     * @return void
     */
    public function encargar(): void
    {
        $this->validate([
            'centroDestinoId' => ['required', 'integer'],
            'areaId' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
            'documentoIds' => ['array'],
            'documentoIds.*' => ['integer'],
        ], [], ['centroDestinoId' => 'centro de destino', 'areaId' => 'área', 'motivo' => 'motivo y contexto']);

        try {
            app(EncargoService::class)->encargar(
                $this->historia,
                $this->plan,
                $this->objetivoId !== null ? PlanObjetivo::where('plan_id', $this->planId)->findOrFail($this->objetivoId) : null,
                Centro::findOrFail($this->centroDestinoId),
                // Sin scope de activas: el servicio rechaza un área inactiva con su mensaje
                AreaEncargo::findOrFail($this->areaId),
                $this->motivo,
                array_map('intval', $this->documentoIds),
                Auth::user(),
            );
        } catch (DomainException $e) {
            $this->addError('motivo', $e->getMessage());

            return;
        }

        $this->modalAbierto = false;
        unset($this->encargos);
        $this->dispatch('derivaciones-cambiadas');
    }

    /**
     * Muestra u oculta el historial de un encargo.
     *
     * @param int $encargoId
     *
     * @return void
     */
    public function verHistorial(int $encargoId): void
    {
        $this->historialDe = $this->historialDe === $encargoId ? null : $encargoId;
    }

    /**
     * Prepara la anulación de un encargo pendiente.
     *
     * @param int $encargoId
     *
     * @return void
     */
    public function prepararAnulacion(int $encargoId): void
    {
        $this->anularId = $encargoId;
        $this->motivoAnulacion = '';
        $this->resetErrorBag();
    }

    /**
     * Anula el encargo preparado (DerivacionService decide si el usuario puede).
     *
     * @return void
     */
    public function anular(): void
    {
        $encargo = Derivacion::query()->encargos()->where('historia_id', $this->historiaId)->findOrFail($this->anularId);

        try {
            app(DerivacionService::class)->anular($encargo, $this->motivoAnulacion, Auth::user());
        } catch (DomainException $e) {
            $this->addError('motivoAnulacion', $e->getMessage());

            return;
        }

        $this->anularId = null;
        unset($this->encargos);
        $this->dispatch('derivaciones-cambiadas');
    }

    /**
     * Si el usuario puede anular el encargo (para mostrar la acción).
     *
     * @param Derivacion $encargo
     *
     * @return bool
     */
    public function puedeAnular(Derivacion $encargo): bool
    {
        return $encargo->estado === EstadoDerivacion::Pendiente && $encargo->derivada_por_id === Auth::id();
    }

    /**
     * Pinta la lista y el modal.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.encargos-profesionales');
    }
}
