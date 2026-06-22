{{-- Bandeja de mensajes: lista de hilos + panel de lectura/redacción --}}
<div class="row g-0 mensajes-bandeja">

    {{-- Columna izquierda: lista de hilos --}}
    <div class="col-md-4 border-end">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Mensajes</h5>
            <button wire:click="nuevaMensaje" class="btn btn-sm btn-primary">
                <x-heroicon-o-pencil-square class="icon-14"/> Nuevo
            </button>
        </div>

        <div class="list-group list-group-flush overflow-auto mensajes-bandeja__list">
            @forelse($this->hilos as $participante)
                @php $hilo = $participante->hilo; $noLeidos = $participante->mensajesNoLeidos(); @endphp
                <div class="list-group-item list-group-item-action {{ $hiloActivoId === $hilo->id ? 'active' : '' }}"
                     wire:click="abrirHilo({{ $hilo->id }})" role="button">

                    <div class="d-flex justify-content-between">
                        <span class="{{ $noLeidos > 0 ? 'fw-bold' : '' }}">
                            {{ $hilo->asunto }}
                        </span>
                        @if($noLeidos > 0)
                            <span class="badge bg-primary rounded-pill">{{ $noLeidos }}</span>
                        @endif
                    </div>

                    <small class="text-muted">
                        {{ $hilo->ultimoMensaje?->created_at?->diffForHumans() ?? '—' }}
                    </small>

                    <div class="mt-1">
                        <button wire:click.stop="archivarHilo({{ $hilo->id }})"
                                class="btn btn-link btn-sm p-0 text-muted"
                                title="Archivar hilo">
                            <x-heroicon-o-archive-box class="icon-14"/>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-3 text-muted small">Sin conversaciones.</div>
            @endforelse
        </div>
    </div>

    {{-- Columna derecha: hilo activo o formulario nuevo mensaje --}}
    <div class="col-md-8">
        @if($mostrarNuevoMensaje)
            <livewire:mensajes-nuevo-mensaje :key="'nuevo-mensaje'" />
        @elseif($hiloActivoId)
            <livewire:mensajes-hilo-mensajes :hiloId="$hiloActivoId" :key="'hilo-'.$hiloActivoId" />
        @else
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <p>Selecciona un hilo o redacta un mensaje nuevo.</p>
            </div>
        @endif
    </div>

</div>
