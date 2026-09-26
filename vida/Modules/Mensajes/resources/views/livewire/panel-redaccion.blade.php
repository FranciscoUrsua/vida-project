{{-- Panel flotante de redacción de mensajes (offcanvas de Bootstrap controlado por Livewire) --}}
<div>
    @if($abierto)
        <aside class="offcanvas offcanvas-end show" tabindex="-1" role="dialog" aria-modal="false" aria-labelledby="panel-redaccion-titulo">
            <div class="offcanvas-header border-bottom">
                <h2 id="panel-redaccion-titulo" class="offcanvas-title h5">Nuevo mensaje</h2>
                <button type="button" class="btn-close" wire:click="cerrar" aria-label="Cerrar"></button>
            </div>

            <form wire:submit="enviar" class="offcanvas-body d-flex flex-column gap-3">

                {{-- Destinatario --}}
                <div>
                    <span class="form-label fw-semibold d-block">Para</span>

                    @if($destinatarioConfirmado)
                        <span class="badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-2 fs-6 fw-normal">
                            {{ $destinatarioConfirmado['nombre'] }}
                            @if($destinatarioConfirmado['cargo']) <span class="opacity-75">· {{ $destinatarioConfirmado['cargo'] }}</span> @endif
                            <button type="button" class="btn-close btn-close-white btn-sm" wire:click="quitarDestinatario" aria-label="Quitar destinatario"></button>
                        </span>
                    @elseif($destinatarioSugerido)
                        {{-- Chip gris: sugerencia orientativa, hay que confirmarla o cambiarla --}}
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge rounded-pill text-bg-light border text-body-secondary d-inline-flex align-items-center gap-2 fs-6 fw-normal">
                                {{ $destinatarioSugerido['nombre'] }}
                                @if($destinatarioSugerido['cargo']) <span>· {{ $destinatarioSugerido['cargo'] }}</span> @endif
                                <span class="small fst-italic">Sin confirmar</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="confirmarSugerencia">Confirmar</button>
                            <button type="button" class="btn btn-sm btn-link" wire:click="quitarDestinatario">Elegir a otra persona</button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="busquedaDestinatario" class="form-control"
                               placeholder="Buscar por nombre" aria-label="Buscar destinatario por nombre" autocomplete="off">
                        <div class="d-flex gap-2 mt-2">
                            <select wire:model.live="filtroCargoId" class="form-select form-select-sm" aria-label="Filtrar por cargo">
                                <option value="">Cualquier cargo</option>
                                @foreach($this->cargos as $cargo)
                                    <option value="{{ $cargo->id }}">{{ $cargo->nombre }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="filtroUoId" class="form-select form-select-sm" aria-label="Filtrar por unidad">
                                <option value="">Cualquier unidad</option>
                                @foreach($this->uos as $uo)
                                    <option value="{{ $uo->id }}">{{ $uo->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($this->resultadosDestinatario->isNotEmpty())
                            <div class="list-group mt-2">
                                @foreach($this->resultadosDestinatario as $usuario)
                                    <button type="button" class="list-group-item list-group-item-action"
                                            wire:key="dest-{{ $usuario->id }}"
                                            wire:click="seleccionarDestinatario({{ $usuario->id }})">
                                        <span class="d-block fw-semibold">{{ $usuario->nombre_completo }}</span>
                                        <span class="d-block small text-body-secondary">
                                            {{ $usuario->profesional?->cargo?->nombre ?? 'Sin cargo' }}
                                            · {{ $usuario->adscripcionesVigentes->first()?->unidadOrganizativa?->nombre ?? 'Sin unidad' }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(mb_strlen(trim($busquedaDestinatario)) >= 2 || $filtroCargoId || $filtroUoId)
                            <p class="small text-body-secondary mt-2 mb-0">No hay profesionales que coincidan.</p>
                        @endif
                    @endif

                    @error('destinatario') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                {{-- Asunto y cuerpo --}}
                <div>
                    <label for="panel-asunto" class="form-label fw-semibold">Asunto</label>
                    <input id="panel-asunto" type="text" wire:model="asunto" maxlength="255"
                           class="form-control @error('asunto') is-invalid @enderror">
                    @error('asunto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="panel-cuerpo" class="form-label fw-semibold">Mensaje</label>
                    <textarea id="panel-cuerpo" wire:model="cuerpo" rows="6" maxlength="10000"
                              class="form-control @error('cuerpo') is-invalid @enderror"></textarea>
                    @error('cuerpo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Elemento vinculado --}}
                <div>
                    <span class="form-label fw-semibold d-block">Elemento vinculado</span>
                    @if($contexto)
                        <span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-2 fs-6 fw-normal">
                            <x-heroicon-o-link class="icon-14" aria-hidden="true"/>
                            {{ $contexto['etiqueta'] }}
                            <button type="button" class="btn-close btn-sm" wire:click="quitarContexto" aria-label="Quitar elemento vinculado"></button>
                        </span>
                    @else
                        <p class="small text-body-secondary mb-0">Ninguno.</p>
                    @endif
                </div>

                {{-- Ciudadanos referenciados --}}
                <div>
                    <span class="form-label fw-semibold d-block">Ciudadanos/as referenciados</span>
                    @if($this->ciudadanosSeleccionados->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach($this->ciudadanosSeleccionados as $ciudadano)
                                <span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-2 fs-6 fw-normal" wire:key="ref-{{ $ciudadano->id }}">
                                    {{ $this->nombreCiudadano($ciudadano) }}
                                    <button type="button" class="btn-close btn-sm" wire:click="quitarCiudadano({{ $ciudadano->id }})" aria-label="Quitar referencia"></button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <input type="search" wire:model.live.debounce.300ms="busquedaCiudadano" class="form-control form-control-sm"
                           placeholder="Añadir por nombre o alias" aria-label="Buscar ciudadano/a para referenciar" autocomplete="off">
                    @if($this->resultadosCiudadano->isNotEmpty())
                        <div class="list-group mt-2">
                            @foreach($this->resultadosCiudadano as $ciudadano)
                                <button type="button" class="list-group-item list-group-item-action small"
                                        wire:key="ciu-{{ $ciudadano->id }}"
                                        wire:click="agregarCiudadano({{ $ciudadano->id }})">
                                    {{ $this->nombreCiudadano($ciudadano) }}
                                    @if($ciudadano->alias) <span class="text-body-secondary">({{ $ciudadano->alias }})</span> @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <div class="form-text">Los mensajes no llevan adjuntos: los documentos se gestionan en la Historia Social.</div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-auto pt-2 border-top">
                    <button type="button" class="btn btn-outline-secondary" wire:click="cerrar">Cancelar</button>
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                        <x-heroicon-o-paper-airplane class="icon-16" aria-hidden="true"/> Enviar
                    </button>
                </div>
            </form>
        </aside>
    @endif
</div>
