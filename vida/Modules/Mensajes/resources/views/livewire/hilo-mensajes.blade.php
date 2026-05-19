{{-- Vista detalle de un hilo de mensajes --}}
<div>
    <div class="p-3 border-bottom">
        <h6 class="mb-0">{{ $this->hilo->asunto }}</h6>
    </div>

    {{-- Lista de mensajes --}}
    <div class="p-3 overflow-auto" style="max-height: 50vh;">
        @foreach($this->hilo->mensajes as $mensaje)
            @php $esMio = $mensaje->remitente_id === auth()->id(); @endphp

            <div class="mb-3 d-flex {{ $esMio ? 'justify-content-end' : 'justify-content-start' }}">
                <div class="card {{ $esMio ? 'bg-primary text-white' : 'bg-light' }}"
                     style="max-width: 70%;">
                    <div class="card-body py-2 px-3">
                        <div class="small {{ $esMio ? 'text-white-50' : 'text-muted' }} mb-1">
                            {{ $mensaje->remitente->name }} · {{ $mensaje->created_at->format('d/m H:i') }}
                        </div>
                        <p class="mb-1">{{ $mensaje->cuerpo }}</p>

                        {{-- Referencias a ciudadanos + botón registrar en historia --}}
                        @foreach($mensaje->referenciasCiudadano as $ref)
                            <div class="mt-1">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-person"></i>
                                    Ciudadano #{{ $ref->ciudadano_id }}
                                </span>
                                @if($this->esTsrDeCiudadano($ref->ciudadano_id))
                                <button wire:click="abrirModalHistoria({{ $mensaje->id }}, {{ $ref->ciudadano_id }})"
                                        class="btn btn-sm btn-link p-0 ms-1
                                               {{ $esMio ? 'text-white' : 'text-primary' }}"
                                        title="Registrar en Historia Social">
                                    <i class="bi bi-journal-plus"></i>
                                </button>
                                @endif
                            </div>
                        @endforeach

                        {{-- Adjuntos --}}
                        @foreach($mensaje->getMedia('adjuntos_mensaje') as $media)
                            <div class="mt-1">
                                <a href="{{ $media->getUrl() }}"
                                   class="{{ $esMio ? 'text-white' : 'text-primary' }}"
                                   target="_blank">
                                    <i class="bi bi-paperclip"></i> {{ $media->file_name }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Caja de respuesta --}}
    <div class="p-3 border-top">
        <form wire:submit="enviarRespuesta">
            <div class="mb-2">
                <textarea wire:model="respuesta"
                          class="form-control"
                          rows="3"
                          placeholder="Escribe tu respuesta..."></textarea>
                @error('respuesta') <span class="text-danger small">{{ $message }}</span> @enderror
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <input type="file" wire:model="adjuntos" multiple class="form-control form-control-sm" />
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-send"></i> Enviar
                </button>
            </div>
        </form>
    </div>

    {{-- Modal: Registrar en Historia Social --}}
    @if($mostrarModalHistoria)
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar en Historia Social</h5>
                        <button wire:click="cerrarModalHistoria" type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Puedes editar el contenido antes de registrarlo. Lo que se registre
                            puede diferir del mensaje original.
                        </p>
                        <div class="mb-3">
                            <label class="form-label">Contenido a registrar</label>
                            <textarea wire:model="cuerpoEditado" class="form-control" rows="5"></textarea>
                            @error('cuerpoEditado') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Visibilidad</label>
                            <select wire:model="visibilidadSeleccionada" class="form-select">
                                @foreach($this->opcionesVisibilidad() as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button wire:click="cerrarModalHistoria" type="button" class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button wire:click="confirmarRegistroHistoria" type="button" class="btn btn-primary">
                            Registrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
