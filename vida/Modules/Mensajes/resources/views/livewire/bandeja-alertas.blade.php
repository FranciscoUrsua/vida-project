{{-- Bandeja de alertas del profesional --}}
<div>
    <h2 class="mb-4">Alertas pendientes</h2>

    @if($this->alertas->isEmpty())
        <div class="alert alert-success">
            <x-heroicon-o-check-circle class="me-2 icon-14"/>No tienes alertas pendientes.
        </div>
    @else
        <div class="list-group">
            @foreach($this->alertas as $alerta)
                <div class="list-group-item list-group-item-action
                    {{ $alerta->tipo->value === 'alerta' ? 'border-warning' : '' }}">

                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            {{-- Indicador de tipo --}}
                            @if($alerta->tipo->value === 'alerta')
                                <span class="badge bg-warning text-dark me-2">
                                    <x-heroicon-s-exclamation-triangle class="icon-14"/> Alerta
                                </span>
                            @else
                                <span class="badge bg-info me-2">
                                    <x-heroicon-o-information-circle class="icon-14"/> Aviso
                                </span>
                            @endif

                            <strong>{{ $alerta->titulo }}</strong>

                            {{-- Tiempo restante para alertas con plazo --}}
                            @if($alerta->expira_en)
                                <small class="text-muted ms-2">
                                    Vence: {{ $alerta->expira_en->diffForHumans() }}
                                </small>
                            @endif

                            <p class="mt-2 mb-1">{{ $alerta->cuerpo }}</p>
                        </div>

                        <div class="ms-3 d-flex flex-column gap-2">
                            @if($alerta->tipo->value === 'alerta')
                                @if($alertaConfirmandoId === $alerta->id)
                                    <span class="text-muted small">¿Confirmar reconocimiento?</span>
                                    <button wire:click="reconocer" class="btn btn-sm btn-success">
                                        Sí, reconocer
                                    </button>
                                    <button wire:click="cancelarReconocimiento" class="btn btn-sm btn-secondary">
                                        Cancelar
                                    </button>
                                @else
                                    <button wire:click="confirmarReconocimiento({{ $alerta->id }})"
                                            class="btn btn-sm btn-outline-success">
                                        <x-heroicon-o-check class="icon-14"/> Reconocer
                                    </button>
                                @endif
                            @else
                                <button wire:click="confirmarReconocimiento({{ $alerta->id }})"
                                        class="btn btn-sm btn-outline-secondary">
                                    <x-heroicon-o-x-mark class="icon-14"/> Descartar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
