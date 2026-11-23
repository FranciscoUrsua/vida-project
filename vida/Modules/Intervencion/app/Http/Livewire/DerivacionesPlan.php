<?php

namespace Modules\Intervencion\Http\Livewire;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Centro\Models\AsignacionCentro;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Centro\Services\ConfiguracionEfectivaCentro;
use Modules\Intervencion\Enums\EstadoDerivacion;
use Modules\Intervencion\Enums\EstadoPlan;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\PlanObjetivo;
use Modules\Intervencion\Services\DerivacionService;
use Modules\Prestaciones\Models\Prestacion;

/**
 * Sección «Derivaciones» del plan principal y modal «Derivar a atención especializada»
 * (fase 3, B4.1).
 *
 * Se incrusta en PlanPage. Solo deriva quien puede editar el plan principal activo;
 * todas las reglas las aplica DerivacionService, esta pantalla solo las refleja.
 *
 * @property-read PlanDeIntervencion $plan
 * @property-read bool $puedeDerivar
 * @property-read Collection<int, Prestacion> $prestaciones
 * @property-read Collection<int, Centro> $centrosDestino
 * @property-read Collection<int, PlanObjetivo> $objetivos
 * @property-read Collection<int, Derivacion> $derivaciones
 */
class DerivacionesPlan extends Component
{
    /** Plan principal del que cuelgan las derivaciones. */
    #[Locked]
    public int $planId;

    public bool $modalAbierto = false;

    public ?int $prestacionId = null;

    public ?int $centroDestinoId = null;

    public ?int $objetivoId = null;

    public string $motivo = '';

    /** Derivación cuyo historial se despliega. */
    public ?int $historialDe = null;

    /** Derivación que se va a anular. */
    public ?int $anularId = null;

    public string $motivoAnulacion = '';

    /**
     * Plan principal (sin scope: PlanPage ya ha autorizado la vista).
     *
     * @return PlanDeIntervencion
     */
    #[Computed]
    public function plan(): PlanDeIntervencion
    {
        return PlanDeIntervencion::withoutGlobalScopes()->findOrFail($this->planId);
    }

    /**
     * Si el usuario puede derivar desde este plan: principal, activo y editable por él.
     *
     * @return bool
     */
    #[Computed]
    public function puedeDerivar(): bool
    {
        return $this->plan->esPrincipal()
            && $this->plan->estado === EstadoPlan::Activo
            && Auth::user()->can('update', $this->plan)
            // R1: derivar es una actuación en el centro del plan
            && app(CentroDeUsuario::class)->puedeActuar(Auth::user(), $this->plan->centro);
    }

    /**
     * Prestaciones de derivación activas.
     *
     * @return Collection<int, Prestacion>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        return Prestacion::query()->derivaciones()->activas()->with('tipoPlanDerivacion')->orderBy('nombre')->get();
    }

    /**
     * Centros activos que ofrecen el tipo de plan de la prestación elegida; primero el
     * centro de ese tipo que la persona tenga asignado, si lo tiene.
     *
     * @return Collection<int, Centro>
     */
    #[Computed]
    public function centrosDestino(): Collection
    {
        $prestacion = $this->prestaciones->firstWhere('id', $this->prestacionId);

        if ($prestacion === null) {
            return collect();
        }

        $configuracion = app(ConfiguracionEfectivaCentro::class);
        $centros = Centro::where('activo', true)->whereNotNull('tipo_centro_id')->orderBy('nombre')->get()
            ->filter(fn (Centro $centro) => $configuracion->tiposPlan($centro)->contains('id', $prestacion->tipo_plan_derivacion_id));

        $asignados = AsignacionCentro::query()->vigentes()
            ->where('ciudadano_id', $this->plan->historia()->withoutGlobalScopes()->value('ciudadano_id'))
            ->pluck('centro_id')
            ->all();

        return $centros->sortBy(fn (Centro $centro) => in_array($centro->id, $asignados, true) ? 0 : 1)->values();
    }

    /**
     * Objetivos del plan principal, para derivar desde uno de ellos.
     *
     * @return Collection<int, PlanObjetivo>
     */
    #[Computed]
    public function objetivos(): Collection
    {
        return PlanObjetivo::where('plan_id', $this->planId)->orderBy('orden')->get();
    }

    /**
     * Derivaciones hechas desde este plan, las más recientes primero.
     *
     * @return Collection<int, Derivacion>
     */
    #[Computed]
    public function derivaciones(): Collection
    {
        return Derivacion::where('plan_origen_id', $this->planId)
            ->with(['centroDestino', 'tipoPlan', 'prestacion', 'objetivo', 'eventos.actor.profesional', 'profesionalResponsable.profesional'])
            ->latest()
            ->get();
    }

    /**
     * Abre el modal de derivación, opcionalmente desde un objetivo (evento de PlanPage).
     *
     * @param int|null $objetivoId
     *
     * @return void
     */
    #[On('abrir-derivacion')]
    public function abrir(?int $objetivoId = null): void
    {
        // R1: rechazo al abrir, con el mensaje de RN-15
        app(CentroDeUsuario::class)->exigirActuacion(Auth::user(), $this->plan->centro);

        if (! $this->puedeDerivar) {
            return;
        }

        $this->reset(['prestacionId', 'centroDestinoId', 'motivo']);
        $this->resetErrorBag();
        $this->objetivoId = $objetivoId;
        $this->modalAbierto = true;

        // Con una sola prestación de derivación, se preselecciona.
        if ($this->prestaciones->count() === 1) {
            $this->prestacionId = $this->prestaciones->first()->id;
        }
    }

    /**
     * Cierra el modal sin derivar.
     *
     * @return void
     */
    public function cerrar(): void
    {
        $this->modalAbierto = false;
    }

    /**
     * Al cambiar de prestación, el centro elegido puede dejar de valer.
     *
     * @return void
     */
    public function updatedPrestacionId(): void
    {
        $this->centroDestinoId = null;
        unset($this->centrosDestino);
    }

    /**
     * Registra la derivación con DerivacionService.
     *
     * @return void
     */
    public function derivar(): void
    {
        $this->validate([
            'prestacionId' => ['required', 'integer'],
            'centroDestinoId' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['prestacionId' => 'prestación', 'centroDestinoId' => 'centro de destino', 'motivo' => 'motivo']);

        try {
            app(DerivacionService::class)->derivar(
                $this->plan,
                Prestacion::findOrFail($this->prestacionId),
                Centro::findOrFail($this->centroDestinoId),
                $this->motivo,
                $this->objetivoId !== null ? PlanObjetivo::where('plan_id', $this->planId)->findOrFail($this->objetivoId) : null,
                Auth::user(),
            );
        } catch (DomainException $e) {
            $this->addError('motivo', $e->getMessage());

            return;
        }

        $this->modalAbierto = false;
        unset($this->derivaciones);
        $this->dispatch('derivaciones-cambiadas');
    }

    /**
     * Muestra u oculta el historial de una derivación.
     *
     * @param int $derivacionId
     *
     * @return void
     */
    public function verHistorial(int $derivacionId): void
    {
        $this->historialDe = $this->historialDe === $derivacionId ? null : $derivacionId;
    }

    /**
     * Prepara la anulación de una derivación pendiente.
     *
     * @param int $derivacionId
     *
     * @return void
     */
    public function prepararAnulacion(int $derivacionId): void
    {
        $this->anularId = $derivacionId;
        $this->motivoAnulacion = '';
        $this->resetErrorBag();
    }

    /**
     * Anula la derivación preparada (el servicio decide si el usuario puede).
     *
     * @return void
     */
    public function anular(): void
    {
        $derivacion = Derivacion::where('plan_origen_id', $this->planId)->findOrFail($this->anularId);

        try {
            app(DerivacionService::class)->anular($derivacion, $this->motivoAnulacion, Auth::user());
        } catch (DomainException $e) {
            $this->addError('motivoAnulacion', $e->getMessage());

            return;
        }

        $this->anularId = null;
        unset($this->derivaciones);
        $this->dispatch('derivaciones-cambiadas');
    }

    /**
     * Si el usuario puede anular la derivación (para mostrar la acción).
     *
     * @param Derivacion $derivacion
     *
     * @return bool
     */
    public function puedeAnular(Derivacion $derivacion): bool
    {
        return $derivacion->estado === EstadoDerivacion::Pendiente && $derivacion->derivada_por_id === Auth::id();
    }

    /**
     * Pinta la sección y el modal.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.derivaciones-plan');
    }
}
