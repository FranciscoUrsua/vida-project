<?php

namespace Modules\Agenda\Livewire\Supervisor\Partials;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\GestionAusenciaService;
use Modules\Centro\Models\Centro;

/**
 * Panel lateral de reasignación de citas por ausencia.
 *
 * Componente hijo que muestra la cita a reasignar y los slots disponibles hoy
 * para el mismo tipo de atención, ordenados urgencias primero.
 * Emite 'citaReasignada' al padre al confirmar.
 *
 * @property-read Cita|null $cita
 * @property-read Collection<int, Slot> $slotsDisponiblesHoy
 * @property int $citaId
 */
class ReasignacionPanel extends Component
{
    /** @var int ID de la cita a reasignar */
    public int $citaId = 0;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Cita que se va a reasignar, con sus relaciones cargadas.
     *
     * @return Cita|null
     */
    #[Computed]
    public function cita(): ?Cita
    {
        if ($this->citaId === 0) {
            return null;
        }

        return Cita::with(['profesional', 'profesional.profesional', 'ciudadano', 'tipoSlot'])
            ->find($this->citaId);
    }

    /**
     * Slots disponibles hoy para reasignar: urgencias primero, luego ordinarios.
     *
     * Excluye al profesional original de la cita y filtra por tipo de slot
     * y el centro del supervisor.
     *
     * @return Collection<int, Slot>
     */
    #[Computed]
    public function slotsDisponiblesHoy(): Collection
    {
        $cita = $this->cita;
        if ($cita === null) {
            return new Collection();
        }

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $urgencias = Slot::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('tipo_slot_id', $cita->tipo_slot_id)
            ->where('estado', EstadoSlot::BloqueadoUrgencia->value)
            ->where('usuario_id', '!=', $cita->profesional_id)
            ->with('usuario', 'usuario.profesional')
            ->orderBy('hora_inicio')
            ->get();

        $ordinarios = Slot::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('tipo_slot_id', $cita->tipo_slot_id)
            ->where('estado', EstadoSlot::Disponible->value)
            ->where('usuario_id', '!=', $cita->profesional_id)
            ->with('usuario', 'usuario.profesional')
            ->orderBy('hora_inicio')
            ->get();

        return $urgencias->merge($ordinarios);
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Confirma la reasignación de la cita al slot seleccionado.
     *
     * Llama al servicio de gestión de ausencias, que crea el registro ReasignacionCita
     * y actualiza la cita y el slot. Despacha 'citaReasignada' al componente padre.
     *
     * @param int $slotId ID del slot destino
     * @return void
     */
    public function confirmarReasignacion(int $slotId): void
    {
        $cita = $this->cita;
        if ($cita === null) {
            return;
        }

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $slot = Slot::where('id', $slotId)
            ->where('centro_id', $centro->id)
            ->whereIn('estado', [EstadoSlot::BloqueadoUrgencia->value, EstadoSlot::Disponible->value])
            ->first();

        if ($slot === null) {
            return;
        }

        app(GestionAusenciaService::class)->reasignar(
            $cita,
            $slot,
            auth()->id(),
            'Reasignación por supervisor'
        );

        unset($this->cita, $this->slotsDisponiblesHoy);

        $this->dispatch('citaReasignada', citaId: $this->citaId);
    }

    /**
     * Renderiza el panel de reasignación.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.partials.reasignacion-panel');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resuelve el Centro del supervisor autenticado.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        return $uoId ? Centro::where('unidad_organizativa_id', $uoId)->first() : null;
    }
}
