<?php

namespace Modules\Mensajes\Http\Livewire;

use App\Models\UsuarioUo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Mensajes\Enums\EstadoAlerta;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\AlertaDestinatario;
use Modules\Mensajes\Services\AlertaService;

/**
 * Pantalla de control de alertas del supervisor (interfaz de Supervisión).
 *
 * - Escaladas: partes de alertas que su equipo no reconoció en plazo; el
 *   supervisor las cierra con «Cerrar alerta», sin plazo (decisión de
 *   2026-09-26).
 * - Aviso al equipo: formulario `NuevoAvisoSupervisor`.
 * - Alertas del equipo: cómo va cada alerta o aviso de las personas de sus
 *   UO (cuántos lo han atendido y el estado de cada uno). No muestra el
 *   cuerpo: el supervisor solo necesita el seguimiento.
 *
 * @property-read Collection<int, AlertaDestinatario> $escaladas
 * @property-read Collection<int, Alerta> $alertasEquipo
 */
#[Layout('layouts.supervision')]
class ControlAlertasPage extends Component
{
    /** Filtro de «Alertas del equipo»: 'abiertas' o 'recientes' (últimos 30 días). */
    public string $filtro = 'abiertas';

    /** Parte escalada cuyo cierre está pendiente de confirmar. */
    public ?int $cierreConfirmandoId = null;

    /**
     * Exige el rol de supervisión.
     *
     * @return void
     */
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('supervision'), 403);
    }

    /**
     * Partes escaladas al supervisor que siguen abiertas, las más antiguas primero.
     *
     * @return Collection<int, AlertaDestinatario>
     */
    #[Computed]
    public function escaladas(): Collection
    {
        return AlertaDestinatario::escaladasA(Auth::user())
            ->with(['alerta', 'usuario'])
            ->orderBy('escalada_en')
            ->get();
    }

    /**
     * Alertas y avisos recibidos por las personas de las UO del supervisor.
     *
     * @return Collection<int, Alerta>
     */
    #[Computed]
    public function alertasEquipo(): Collection
    {
        $uoIds = Auth::user()->adscripcionesVigentes()->pluck('unidad_organizativa_id');
        $equipo = UsuarioUo::whereIn('unidad_organizativa_id', $uoIds)->vigentes()->pluck('usuario_id');

        $consulta = Alerta::whereHas('destinatarios', fn ($d) => $d->whereIn('usuario_id', $equipo))
            ->with(['destinatarios.usuario'])
            ->latest()
            ->limit(100);

        if ($this->filtro === 'recientes') {
            $consulta->where('created_at', '>=', now()->subDays(30));
        } else {
            $consulta->whereIn('estado', [EstadoAlerta::Pendiente, EstadoAlerta::Escalada]);
        }

        return $consulta->get();
    }

    /**
     * Pide confirmación antes de cerrar una parte escalada.
     *
     * @param int $destinatarioId ID de la parte escalada.
     * @return void
     */
    public function confirmarCierre(int $destinatarioId): void
    {
        $this->cierreConfirmandoId = $destinatarioId;
    }

    /**
     * Cancela el diálogo de confirmación.
     *
     * @return void
     */
    public function cancelarCierre(): void
    {
        $this->cierreConfirmandoId = null;
    }

    /**
     * Cierra la parte escalada confirmada («Cerrar alerta»).
     *
     * @param AlertaService $alertaService Servicio de ciclo de vida de alertas.
     * @return void
     *
     * @throws AuthorizationException Si la parte no está escalada a este supervisor.
     */
    public function cerrar(AlertaService $alertaService): void
    {
        if (! $this->cierreConfirmandoId) {
            return;
        }

        $parte = AlertaDestinatario::escaladasA(Auth::user())->find($this->cierreConfirmandoId);

        if (! $parte) {
            throw new AuthorizationException('Esta alerta no está escalada a ti.');
        }

        $alertaService->cerrarEscalada($parte, Auth::user(), request()->ip() ?? '');

        $this->cierreConfirmandoId = null;
        unset($this->escaladas, $this->alertasEquipo);
    }

    /**
     * Refresca el seguimiento tras enviar un aviso al equipo.
     *
     * @return void
     */
    #[On('aviso-enviado')]
    public function refrescar(): void
    {
        unset($this->alertasEquipo);
    }

    /**
     * Renderiza la pantalla.
     *
     * @return View
     */
    public function render(): View
    {
        return view('mensajes::livewire.control-alertas-page');
    }
}
