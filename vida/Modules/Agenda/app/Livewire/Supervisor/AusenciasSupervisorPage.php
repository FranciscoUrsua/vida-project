<?php

namespace Modules\Agenda\Livewire\Supervisor;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\Slot;
use Modules\Centro\Models\Centro;

/**
 * Página de ausencias del día para el supervisor de centro.
 *
 * Muestra dos secciones diferenciadas:
 * - Ausencias sobrevenidas: profesionales con excepción activa hoy y sus citas canceladas.
 * - No-shows de ciudadanos: citas donde el ciudadano no se presentó hoy.
 *
 * El panel lateral de reasignación se gestiona como offcanvas de Bootstrap.
 *
 * @property int $citaSeleccionadaId
 * @property bool $panelAbierto
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class AusenciasSupervisorPage extends Component
{
    /** @var int ID de la cita seleccionada para reasignar (0 = ninguna) */
    public int $citaSeleccionadaId = 0;

    /** @var bool Indica si el panel lateral de reasignación está visible */
    public bool $panelAbierto = false;

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    /**
     * Excepciones profesionales activas hoy en el centro del supervisor.
     *
     * Solo incluye excepciones que afectan la disponibilidad y cuya franja
     * de inicio <= hoy y fin >= hoy.
     *
     * @return Collection<int, ExcepcionProfesional>
     */
    #[Computed]
    public function ausenciasHoy(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        $hoy = now()->toDateString();

        return ExcepcionProfesional::where('centro_id', $centro->id)
            ->where('afecta_disponibilidad', true)
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->with(['usuario', 'usuario.profesional'])
            ->get();
    }

    /**
     * Citas canceladas por ausencia hoy en el centro, pendientes de gestionar.
     *
     * Agrupadas por profesional_id para facilitar el renderizado por panel.
     *
     * @return Collection<int, Cita>
     */
    #[Computed]
    public function citasPendientes(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        return Cita::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::Cancelada->value)
            ->where('motivo_cancelacion', 'like', '%Ausencia del profesional%')
            ->whereDoesntHave('reasignacion')
            ->with(['profesional', 'profesional.profesional', 'ciudadano', 'tipoSlot', 'reasignacion'])
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Citas en estado no-show ciudadano de hoy en el centro.
     *
     * @return Collection<int, Cita>
     */
    #[Computed]
    public function noshowsCiudadanos(): Collection
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return new Collection();
        }

        return Cita::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::NoShowCiudadano->value)
            ->with(['profesional', 'profesional.profesional', 'ciudadano', 'tipoSlot'])
            ->orderBy('hora_inicio')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Abre el panel de reasignación para la cita indicada.
     *
     * @param int $citaId
     * @return void
     */
    public function abrirReasignacion(int $citaId): void
    {
        $this->citaSeleccionadaId = $citaId;
        $this->panelAbierto = true;
    }

    /**
     * Descarta la cita cancelada sin reasignarla.
     *
     * La cita permanece cancelada con un motivo que indica el descarte explícito.
     * No crea ReasignacionCita.
     *
     * @param int $citaId
     * @return void
     */
    public function descartar(int $citaId): void
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $cita = Cita::where('id', $citaId)
            ->where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::Cancelada->value)
            ->first();

        if ($cita === null) {
            return;
        }

        $cita->update([
            'motivo_cancelacion' => 'Ausencia del profesional — descartada por supervisor',
        ]);

        unset($this->citasPendientes);
    }

    /**
     * Libera el slot de un no-show ciudadano, devolviéndolo a estado disponible.
     *
     * @param int $citaId
     * @return void
     */
    public function liberarSlot(int $citaId): void
    {
        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return;
        }

        $cita = Cita::where('id', $citaId)
            ->where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::NoShowCiudadano->value)
            ->first();

        if ($cita === null) {
            return;
        }

        if ($cita->slot_id) {
            Slot::where('id', $cita->slot_id)->update(['estado' => EstadoSlot::Disponible->value]);
        }

        unset($this->noshowsCiudadanos);
    }

    /**
     * Recibe el evento del panel hijo cuando se confirma una reasignación.
     *
     * @return void
     */
    public function cerrarPanelReasignacion(): void
    {
        $this->panelAbierto = false;
        $this->citaSeleccionadaId = 0;
        unset($this->citasPendientes);
    }

    /**
     * Renderiza la pantalla de ausencias del día.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.ausencias-supervisor-page');
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
