<?php

namespace Modules\Agenda\Livewire\Supervisor;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Centro\Models\Centro;

/**
 * Sidebar del interfaz operativo de Agenda — supervisor de centro.
 *
 * Muestra la navegación de las cuatro pantallas de agenda con badge de
 * citas de ausencia pendientes de gestionar. Se refresca cada 30 segundos.
 *
 * @property int $citasPendientesBadge
 */
class Sidebar extends Component
{
    /**
     * Número de citas canceladas por ausencia sin gestionar hoy.
     *
     * @return int
     */
    #[Computed]
    public function citasPendientesBadge(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $centro = $this->centroDelSupervisor();
        if ($centro === null) {
            return 0;
        }

        return Cita::where('centro_id', $centro->id)
            ->where('fecha', now()->toDateString())
            ->where('estado', EstadoCita::Cancelada->value)
            ->where('motivo_cancelacion', 'like', '%Ausencia del profesional%')
            ->whereDoesntHave('reasignacion')
            ->count();
    }

    /**
     * Renderiza el sidebar del módulo de agenda.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.supervisor.sidebar');
    }

    /**
     * Resuelve el Centro del supervisor autenticado por su primera UO activa.
     *
     * @return Centro|null
     */
    private function centroDelSupervisor(): ?Centro
    {
        $uoId = auth()->user()?->uosActivas()->first()?->id;

        return $uoId
            ? Centro::where('unidad_organizativa_id', $uoId)->first()
            : null;
    }
}
