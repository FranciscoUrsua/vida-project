{{-- Badge de notificaciones para la barra de navegación --}}
{{-- Se actualiza cada 60 segundos via wire:poll --}}
<div wire:poll.{{ $intervalo }}s>
    @if($this->total > 0)
        <span class="badge rounded-pill bg-danger">
            {{ $this->total }}
        </span>
    @endif

    @if($this->totalAlertas > 0)
        <a href="{{ url('/mensajes/alertas') }}" class="text-decoration-none me-2" title="Alertas pendientes">
            <i class="bi bi-bell-fill text-warning"></i>
            <span class="badge bg-warning text-dark">{{ $this->totalAlertas }}</span>
        </a>
    @endif

    @if($this->totalMensajes > 0)
        <a href="{{ url('/mensajes') }}" class="text-decoration-none" title="Mensajes no leídos">
            <i class="bi bi-chat-fill"></i>
            <span class="badge bg-primary">{{ $this->totalMensajes }}</span>
        </a>
    @endif
</div>
