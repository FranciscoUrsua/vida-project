<?php

namespace Modules\Mensajes\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Mensajes\Enums\DestinatarioType;
use Modules\Mensajes\Enums\EstadoAlerta;
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

    #[Computed]
    public function totalAlertas(): int
    {
        if (! auth()->check()) {
            return 0;
        }

        $usuario = auth()->user();

        // Alertas directas al usuario
        $directas = Alerta::where('estado', EstadoAlerta::Pendiente)
            ->where('destinatario_type', DestinatarioType::Usuario)
            ->where('destinatario_usuario_id', $usuario->id)
            ->count();

        // Alertas por rol+UO que corresponden al usuario
        $porRol = Alerta::where('estado', EstadoAlerta::Pendiente)
            ->where('destinatario_type', DestinatarioType::RolUo)
            ->whereHas('destinatarioUo', function ($query) use ($usuario) {
                $query->whereHas('usuarios', function ($q) use ($usuario) {
                    $q->where('usuario_id', $usuario->id);
                });
            })
            ->whereIn('destinatario_rol', $usuario->getRoleNames()->all())
            ->count();

        return $directas + $porRol;
    }

    #[Computed]
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
    public function total(): int
    {
        return $this->totalAlertas + $this->totalMensajes;
    }

    public function render(): \Illuminate\View\View
    {
        return view('mensajes::livewire.badge-notificaciones');
    }
}
