<?php

namespace Modules\Prestaciones\Http\Livewire;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Prestaciones\Enums\EstadoPrestacionPersona;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\PrestacionPersona;
use Modules\Prestaciones\Models\SolicitudPrestacion;
use Modules\Prestaciones\Services\PrestacionPersonaService;

/**
 * Bloque «Prestaciones» de la ficha de la persona (fase 3 bis, paso 5.5).
 *
 * Activas, concedidas pendientes de inicio (con los días desde la concesión), suspendidas y
 * solicitudes en curso. Acciones de registrar inicio, suspender, reanudar y finalizar para
 * quien puede gestionarlas. No se muestra a quien no tiene `solicitud.ver` (`consulta_basica`).
 *
 * @property-read Collection<int, PrestacionPersona> $prestaciones
 * @property-read Collection<int, SolicitudPrestacion> $solicitudes
 */
class PrestacionesPersona extends Component
{
    #[Locked]
    public int $ciudadanoId;

    /** Prestación sobre la que se actúa. */
    public ?int $accionId = null;

    /** inicio, suspender, finalizar. */
    public ?string $accion = null;

    public ?string $fecha = null;

    public string $motivo = '';

    /**
     * Prestaciones vigentes de la persona (no finalizadas).
     *
     * @return Collection<int, PrestacionPersona>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        return PrestacionPersona::with('prestacion')
            ->where('ciudadano_id', $this->ciudadanoId)
            ->where('estado', '!=', EstadoPrestacionPersona::Finalizada->value)
            ->orderByRaw("CASE estado WHEN 'activa' THEN 0 WHEN 'concedida' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Solicitudes en curso de la persona.
     *
     * @return Collection<int, SolicitudPrestacion>
     */
    #[Computed]
    public function solicitudes(): Collection
    {
        return SolicitudPrestacion::with('prestacion')
            ->where('ciudadano_id', $this->ciudadanoId)
            ->whereIn('estado', array_map(fn ($e) => $e->value, array_filter(EstadoSolicitudPrestacion::cases(), fn ($e) => $e->enCurso())))
            ->latest()
            ->get();
    }

    /**
     * Abre una acción sobre una prestación.
     *
     * @param int $id
     * @param string $accion
     *
     * @return void
     */
    public function preparar(int $id, string $accion): void
    {
        $this->resetErrorBag();
        $this->accionId = $id;
        $this->accion = $accion;
        $this->fecha = today()->toDateString();
        $this->motivo = '';
    }

    /**
     * Ejecuta la acción preparada.
     *
     * @return void
     */
    public function confirmar(): void
    {
        $prestacion = $this->prestaciones->firstWhere('id', $this->accionId);
        if ($prestacion === null) {
            return;
        }

        $servicio = app(PrestacionPersonaService::class);
        $fecha = Carbon::parse($this->fecha ?? today());

        try {
            match ($this->accion) {
                'inicio' => $servicio->registrarInicio($prestacion, $fecha, Auth::user()),
                'suspender' => $servicio->suspender($prestacion, $this->motivo, Auth::user()),
                'reanudar' => $servicio->reanudar($prestacion, Auth::user()),
                'finalizar' => $servicio->finalizar($prestacion, $fecha, $this->motivo, Auth::user()),
                default => null,
            };
        } catch (AuthorizationException|DomainException $e) {
            $this->addError('accion', $e->getMessage());

            return;
        }

        $this->reset(['accionId', 'accion', 'motivo']);
        unset($this->prestaciones);
    }

    /**
     * Si el usuario puede gestionar la prestación.
     *
     * @param PrestacionPersona $prestacion
     *
     * @return bool
     */
    public function puedeGestionar(PrestacionPersona $prestacion): bool
    {
        return Gate::allows('gestionar', $prestacion);
    }

    /**
     * Recarga tras registrar una prestación o una solicitud.
     *
     * @return void
     */
    #[On('prestaciones-cambiadas')]
    public function refrescar(): void
    {
        unset($this->prestaciones, $this->solicitudes);
    }

    /**
     * Pinta el bloque (nada si el usuario no ve prestaciones).
     *
     * @return View
     */
    public function render(): View
    {
        return view('prestaciones::livewire.prestaciones-persona', ['visible' => Auth::user()->can('solicitud.ver')]);
    }
}
