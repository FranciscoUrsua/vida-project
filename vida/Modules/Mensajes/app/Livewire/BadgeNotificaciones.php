<?php

namespace Modules\Mensajes\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Models\Alerta;
use Modules\Mensajes\Models\MensajeParticipante;

/**
 * Badge embebible en la barra de navegación que muestra el recuento
 * de alertas pendientes y mensajes no leídos del usuario autenticado.
 *
 * Se actualiza cada 60 segundos mediante wire:poll.
 *
 * // TODO mejora futura: reemplazar polling por Laravel Echo + broadcasting
 *           para reducir carga con muchos usuarios concurrentes.
 */
class BadgeNotificaciones extends Component
{
    /** @var int Intervalo de polling en segundos */
    public int $intervalo = 60;

    /**
     * Total de alertas y avisos pendientes visibles para el usuario.
     *
     * @return int
     */
    #[Computed]
    public function totalAlertas(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return Alerta::visiblesPara(auth()->user())->pendientes()->count();
    }

    #[Computed]
    /**
     * Total de mensajes no leídos en hilos activos.
     */
    public function totalMensajes(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        return MensajeParticipante::where('usuario_id', auth()->id())
            ->whereNull('archivado_en')
            ->get()
            ->sum(fn (MensajeParticipante $p) => $p->mensajesNoLeidos());
    }

    #[Computed]
    /**
     * Total agregado de alertas y mensajes.
     */
    public function total(): int
    {
        return $this->totalAlertas + $this->totalMensajes;
    }

    /**
     * Renderiza el badge de notificaciones.
     */
    public function render(): View
    {
        return view('mensajes::livewire.badge-notificaciones');
    }
}
