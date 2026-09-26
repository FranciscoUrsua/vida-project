{{-- Pestaña de mensajes: lista de hilos + hilo abierto o formulario de mensaje nuevo --}}
<div class="card">
    <div class="row g-0 mensajes-bandeja">

        {{-- Lista de hilos --}}
        <div class="col-md-4 border-end">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                <span class="h6 fw-semibold text-body-secondary mb-0">Conversaciones</span>
                <button type="button" wire:click="nuevaMensaje" class="btn btn-sm btn-primary ms-auto d-inline-flex align-items-center gap-1">
                    <x-heroicon-o-pencil-square class="icon-14" aria-hidden="true"/> Nuevo mensaje
                </button>
            </div>

            <div class="list-group list-group-flush overflow-auto mensajes-bandeja__list">
                @forelse($this->hilos as $participante)
                    @php
                        $hilo = $participante->hilo;
                        $noLeidos = $participante->mensajesNoLeidos();
                        $otro = $hilo->participantes->firstWhere('usuario_id', '!=', auth()->id())?->usuario;
                    @endphp
                    <div class="list-group-item list-group-item-action {{ $hiloActivoId === $hilo->id ? 'active' : '' }}"
                         wire:key="hilo-{{ $hilo->id }}"
                         wire:click="abrirHilo({{ $hilo->id }})" role="button">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="{{ $noLeidos > 0 ? 'fw-bold' : '' }} text-truncate">{{ $hilo->asunto }}</span>
                            @if($noLeidos > 0)
                                <span class="badge rounded-pill text-bg-primary">{{ $noLeidos }}<span class="visually-hidden"> sin leer</span></span>
                            @endif
                        </div>
                        <div class="d-flex justify-content-between align-items-center gap-2 small">
                            <span class="text-truncate">{{ $otro?->nombre_completo ?? '—' }}</span>
                            <span class="d-inline-flex align-items-center gap-2 flex-shrink-0">
                                {{ $hilo->ultimoMensaje?->created_at?->format('d/m/Y H:i') ?? '—' }}
                                <button type="button" wire:click.stop="archivarHilo({{ $hilo->id }})"
                                        class="btn btn-link btn-sm p-0 {{ $hiloActivoId === $hilo->id ? 'text-white' : 'text-body-secondary' }}"
                                        title="Archivar conversación" aria-label="Archivar conversación">
                                    <x-heroicon-o-archive-box class="icon-14" aria-hidden="true"/>
                                </button>
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="op-empty">
                        <x-heroicon-o-chat-bubble-left-right class="op-empty__icon" aria-hidden="true"/>
                        <p class="op-empty__text">No tienes conversaciones.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Hilo abierto o mensaje nuevo --}}
        <div class="col-md-8">
            @if($mostrarNuevoMensaje)
                <livewire:mensajes-nuevo-mensaje :key="'nuevo-mensaje'" />
            @elseif($hiloActivoId)
                <livewire:mensajes-hilo-mensajes :hiloId="$hiloActivoId" :key="'hilo-'.$hiloActivoId" />
            @else
                <div class="op-empty">
                    <p class="op-empty__text">Selecciona una conversación o escribe un mensaje nuevo.</p>
                </div>
            @endif
        </div>

    </div>
</div>
