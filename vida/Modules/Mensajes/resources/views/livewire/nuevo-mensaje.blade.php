{{-- Formulario de redacción de nuevo mensaje --}}
<div class="p-3">
    <h6 class="mb-3">Nuevo mensaje</h6>

    <form wire:submit="enviar">

        {{-- Destinatario --}}
        <div class="mb-3">
            <label class="form-label">Destinatario</label>
            @if($destinatarioId)
                @php $dest = \App\Models\User::find($destinatarioId); @endphp
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary fs-6">{{ $dest?->name }}</span>
                    <button wire:click="limpiarDestinatario" type="button"
                            class="btn btn-sm btn-outline-secondary">
                        <x-heroicon-o-x-mark class="icon-14"/> Cambiar
                    </button>
                </div>
            @else
                <input type="text"
                       wire:model.live="busquedaDestinatario"
                       class="form-control"
                       placeholder="Buscar por nombre..." />

                {{-- Filtros opcionales por rol y UO --}}
                <div class="row mt-2">
                    <div class="col-6">
                        <input type="text" wire:model.live="filtroPorRol"
                               class="form-control form-control-sm"
                               placeholder="Filtrar por rol" />
                    </div>
                    <div class="col-6">
                        <select wire:model.live="filtroUoId" class="form-select form-select-sm">
                            <option value="">Cualquier UO</option>
                            @foreach(\App\Models\UnidadOrganizativa::orderBy('nombre')->get() as $uo)
                                <option value="{{ $uo->id }}">{{ $uo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Resultados de búsqueda --}}
                @if($this->resultadosDestinatario->isNotEmpty())
                    <div class="list-group mt-1">
                        @foreach($this->resultadosDestinatario as $usuario)
                            <button type="button"
                                    wire:click="seleccionarDestinatario({{ $usuario->id }})"
                                    class="list-group-item list-group-item-action py-2">
                                <strong>{{ $usuario->name }}</strong>
                                <small class="text-muted ms-2">
                                    {{ $usuario->adscripciones->first()?->unidadOrganizativa?->nombre ?? '—' }}
                                </small>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
            @error('destinatarioId') <span class="text-danger small">{{ $message }}</span> @enderror
        </div>

        {{-- Asunto --}}
        <div class="mb-3">
            <label class="form-label">Asunto</label>
            <input type="text" wire:model="asunto" class="form-control" maxlength="255" />
            @error('asunto') <span class="text-danger small">{{ $message }}</span> @enderror
        </div>

        {{-- Cuerpo --}}
        <div class="mb-3">
            <label class="form-label">Mensaje</label>
            <textarea wire:model="cuerpo" class="form-control" rows="5"></textarea>
            @error('cuerpo') <span class="text-danger small">{{ $message }}</span> @enderror
        </div>

        {{-- Referencias a ciudadanos --}}
        <div class="mb-3">
            <label class="form-label">Ciudadanos referenciados <span class="text-muted small">(opcional)</span></label>

            @foreach($ciudadanosSeleccionados as $cid)
                <div class="badge bg-secondary me-1 mb-1">
                    Ciudadano #{{ $cid }}
                    <button wire:click="quitarCiudadano({{ $cid }})" type="button"
                            class="btn-close btn-close-white btn-sm ms-1"></button>
                </div>
            @endforeach

            <input type="text"
                   wire:model.live="busquedaCiudadano"
                   class="form-control form-control-sm mt-1"
                   placeholder="Buscar ciudadano..." />

            @if($this->resultadosCiudadano->isNotEmpty())
                <div class="list-group mt-1">
                    @foreach($this->resultadosCiudadano as $ciudadano)
                        <button type="button"
                                wire:click="agregarCiudadano({{ $ciudadano->id }})"
                                class="list-group-item list-group-item-action py-1 small">
                            #{{ $ciudadano->id }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Adjuntos --}}
        <div class="mb-3">
            <label class="form-label">Adjuntos <span class="text-muted small">(opcional)</span></label>
            <input type="file" wire:model="adjuntos" multiple class="form-control" />
            @error('adjuntos.*') <span class="text-danger small">{{ $message }}</span> @enderror
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <x-heroicon-o-paper-airplane class="icon-14"/> Enviar
            </button>
        </div>
    </form>
</div>
