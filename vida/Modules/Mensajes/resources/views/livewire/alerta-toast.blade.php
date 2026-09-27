{{--
    Toasts persistentes de alertas pendientes (modulo-mensajes.md §4.2).
    Sin auto-dismiss. Lo minimizado lo decide el servidor (sesión de Laravel):
    sin estado en Alpine, que el morph de Livewire no respetaba. Se muestran
    como mucho AlertaToast::MAXIMO_VISIBLES; el resto se resume en una línea.
--}}
@php($restantes = max(0, $this->visibles->count() - \Modules\Mensajes\Livewire\AlertaToast::MAXIMO_VISIBLES))
<div wire:poll.60s
     class="toast-container position-fixed start-50 translate-middle-x p-3 mensajes-toasts"
     aria-live="assertive">
    @foreach($this->visibles->take(\Modules\Mensajes\Livewire\AlertaToast::MAXIMO_VISIBLES) as $alerta)
        <div class="toast show border-danger"
             role="alert"
             aria-atomic="true"
             wire:key="alerta-toast-{{ $alerta->id }}">
            <div class="toast-header gap-2">
                <x-heroicon-s-exclamation-triangle class="icon-16 flex-shrink-0" aria-hidden="true"/>
                <strong class="me-auto">Alerta</strong>
                @if($alerta->expira_en)
                    <small class="{{ $alerta->expira_en->isPast() ? 'fw-semibold' : '' }}">
                        {{ $alerta->expira_en->isPast() ? 'Plazo vencido' : 'Vence '.$alerta->expira_en->diffForHumans() }}
                    </small>
                @endif
                <button type="button"
                        class="btn btn-sm btn-link text-reset p-0 ms-1"
                        wire:click="minimizar({{ $alerta->id }})"
                        title="Minimizar: volverá a aparecer en 30 minutos"
                        aria-label="Minimizar la alerta «{{ $alerta->titulo }}». Volverá a aparecer en 30 minutos">
                    <x-heroicon-o-minus class="icon-16" aria-hidden="true"/>
                </button>
            </div>

            <div class="toast-body d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="flex-grow-1">
                    <p class="fw-semibold mb-1">{{ $alerta->titulo }}</p>
                    <p class="small mb-0">{{ $alerta->cuerpo }}</p>
                </div>

                <div class="d-flex flex-column align-items-md-end gap-2 flex-shrink-0">
                    @if($alertaConfirmandoId === $alerta->id)
                        <span class="small text-body-secondary">¿Confirmas que la has leído?</span>
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="cancelarReconocimiento" class="btn btn-sm btn-outline-secondary">Cancelar</button>
                            <button type="button" wire:click="reconocer" class="btn btn-sm btn-primary">Sí, reconocer</button>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2">
                            @if($url = $this->enlaceOrigen($alerta))
                                <a href="{{ $url }}" class="btn btn-sm btn-link">Ver origen</a>
                            @endif
                            <button type="button"
                                    wire:click="confirmarReconocimiento({{ $alerta->id }})"
                                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                <x-heroicon-o-check class="icon-14" aria-hidden="true"/> Reconocer
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    @if($restantes > 0)
        <div class="toast show" wire:key="alerta-toast-resto">
            <div class="toast-body d-flex align-items-center gap-2">
                <span class="me-auto small">
                    Y <strong>{{ $restantes }}</strong> {{ $restantes === 1 ? 'alerta pendiente más' : 'alertas pendientes más' }}
                </span>
                @if($urlBandeja)
                    <a href="{{ $urlBandeja }}" class="btn btn-sm btn-link">Ver todas</a>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="minimizarTodas">Minimizar todas</button>
            </div>
        </div>
    @endif
</div>
