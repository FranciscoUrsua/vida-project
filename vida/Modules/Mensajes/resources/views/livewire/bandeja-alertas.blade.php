{{-- Pestaña de alertas o avisos de la bandeja --}}
<div>
    @if($this->alertas->isEmpty())
        <div class="op-empty">
            <x-heroicon-o-check-circle class="op-empty__icon" aria-hidden="true"/>
            <p class="op-empty__text">
                {{ $tipo === 'alerta' ? 'No tienes alertas pendientes.' : 'No tienes avisos pendientes.' }}
            </p>
        </div>
    @else
        <ul class="list-group">
            @foreach($this->alertas as $alerta)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3" wire:key="alerta-{{ $alerta->id }}">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            @if($alerta->origen_type === \Modules\Mensajes\Services\AlertaService::ORIGEN_SUPERVISOR)
                                <span class="badge rounded-pill text-bg-primary">Aviso del supervisor</span>
                            @endif
                            <span class="fw-semibold">{{ $alerta->titulo }}</span>
                        </div>

                        <p class="mb-1">{{ $alerta->cuerpo }}</p>

                        @if($alerta->expira_en)
                            <small class="{{ $alerta->expira_en->isPast() ? 'text-danger fw-semibold' : 'text-body-secondary' }} d-inline-flex align-items-center gap-1">
                                <x-heroicon-o-clock class="icon-14" aria-hidden="true"/>
                                {{ $alerta->expira_en->isPast() ? 'Plazo vencido' : 'Vence '.$alerta->expira_en->diffForHumans() }}
                                ({{ $alerta->expira_en->format('d/m/Y H:i') }})
                            </small>
                        @else
                            <small class="text-body-secondary">{{ $alerta->created_at->format('d/m/Y H:i') }}</small>
                        @endif
                    </div>

                    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                        @if($tipo === 'alerta')
                            @if($alertaConfirmandoId === $alerta->id)
                                <span class="small text-body-secondary">¿Confirmas que la has leído?</span>
                                <div class="d-flex gap-2">
                                    <button type="button" wire:click="cancelarReconocimiento" class="btn btn-sm btn-outline-secondary">Cancelar</button>
                                    <button type="button" wire:click="reconocer" class="btn btn-sm btn-primary">Sí, reconocer</button>
                                </div>
                            @else
                                <button type="button" wire:click="confirmarReconocimiento({{ $alerta->id }})" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                    <x-heroicon-o-check class="icon-14" aria-hidden="true"/> Reconocer
                                </button>
                            @endif
                        @else
                            <button type="button" wire:click="descartar({{ $alerta->id }})" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                                <x-heroicon-o-x-mark class="icon-14" aria-hidden="true"/> Descartar
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
