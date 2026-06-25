<div class="op-page">

    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <button type="button" class="btn btn-primary btn-sm ms-auto" wire:click="abrirModal">
            <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
            Nueva actividad
        </button>
    </div>

    <section class="p-3">
        @if($this->actividades->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-user-group class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay actividades programadas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Modo de acceso</th>
                            <th>Aforo</th>
                            <th>Fecha alta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->actividades as $actividad)
                        <tr>
                            <td>
                                <button type="button" class="btn btn-link p-0 text-start fw-medium text-decoration-none text-body"
                                        wire:click="abrirEdicion({{ $actividad->id }})">
                                    {{ $actividad->nombre }}
                                </button>
                            </td>
                            <td class="text-body-secondary small">{{ $actividad->tipoActividad?->nombre ?? '—' }}</td>
                            <td>
                                @php $modos = ['libre' => 'Libre', 'prescripcion' => 'Prescripción', 'mixta' => 'Mixta']; @endphp
                                {{ $modos[$actividad->modo_acceso] ?? $actividad->modo_acceso }}
                            </td>
                            <td>{{ $actividad->aforo_total ?? '—' }}</td>
                            <td class="text-body-secondary small">{{ $actividad->fecha_alta?->format('d/m/Y') }}</td>
                            <td>
                                @if($actividad->activa)
                                    <span class="badge bg-success-subtle text-success-emphasis">Activa</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactiva</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Modal actividad (alta y edición) --}}
    @if($modalAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-actividad-titulo">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-actividad-titulo">
                        {{ $editandoId ? 'Editar actividad' : 'Nueva actividad' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('modalAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label" for="act-nombre">Nombre <span class="text-danger">*</span></label>
                        <input id="act-nombre" type="text" class="form-control @error('nombre') is-invalid @enderror"
                               wire:model="nombre" maxlength="200" autofocus>
                        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="act-tipo">Tipo de actividad <span class="text-danger">*</span></label>
                            <select id="act-tipo" class="form-select @error('tipoActividadId') is-invalid @enderror"
                                    wire:model="tipoActividadId">
                                <option value="">Selecciona un tipo…</option>
                                @foreach($this->tiposActividad as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                            @error('tipoActividadId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="act-modo">Modo de acceso <span class="text-danger">*</span></label>
                            <select id="act-modo" class="form-select @error('modoAcceso') is-invalid @enderror"
                                    wire:model.live="modoAcceso">
                                <option value="libre">Libre</option>
                                <option value="prescripcion">Prescripción</option>
                                <option value="mixta">Mixta</option>
                            </select>
                            @error('modoAcceso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label" for="act-aforo">Aforo total</label>
                            <input id="act-aforo" type="number" min="1" class="form-control @error('aforoTotal') is-invalid @enderror"
                                   wire:model="aforoTotal" placeholder="Sin límite">
                            @error('aforoTotal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @if($modoAcceso !== 'libre')
                        <div class="col-sm-4">
                            <label class="form-label" for="act-aforo-presc">Aforo prescripción</label>
                            <input id="act-aforo-presc" type="number" min="0" class="form-control @error('aforoPresc') is-invalid @enderror"
                                   wire:model="aforoPresc">
                            @error('aforoPresc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @endif
                        <div class="col-sm-4">
                            <label class="form-label" for="act-fecha">Fecha de alta <span class="text-danger">*</span></label>
                            <input id="act-fecha" type="date" class="form-control @error('fechaAlta') is-invalid @enderror"
                                   wire:model="fechaAlta">
                            @error('fechaAlta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="act-inscripcion"
                               wire:model="requiereInscripcion">
                        <label class="form-check-label" for="act-inscripcion">
                            Requiere inscripción previa al centro
                        </label>
                    </div>

                    {{-- Profesionales responsables --}}
                    <div class="border-top pt-3">
                        <p class="fw-semibold mb-2">
                            Profesionales responsables <span class="text-danger">*</span>
                        </p>

                        @error('profesionalesIds')
                            <div class="alert alert-warning py-2 px-3 small mb-2">{{ $message }}</div>
                        @enderror

                        {{-- Asignados --}}
                        @if($this->profesionalesAsignados->isNotEmpty())
                        <ul class="list-group list-group-flush mb-3">
                            @foreach($this->profesionalesAsignados as $prof)
                            <li class="list-group-item d-flex align-items-center justify-content-between px-0 py-1">
                                <span class="small">
                                    <x-heroicon-s-user-circle class="icon-16 text-secondary me-1" aria-hidden="true"/>
                                    {{ $prof->nombre_completo }}
                                    @if($prof->cargo)
                                        <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                                    @endif
                                </span>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 text-danger"
                                        wire:click="quitarProfesional({{ $prof->id }})"
                                        aria-label="Quitar a {{ $prof->nombre_completo }}">
                                    <x-heroicon-o-x-mark class="icon-16" aria-hidden="true"/>
                                </button>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-body-secondary small mb-3">Sin profesionales asignados todavía.</p>
                        @endif

                        {{-- Picker: modo centro (lista directa) --}}
                        @if(! $buscarEnTodo)
                            @if($this->profesionalesParaSelector->isNotEmpty())
                            <div class="border rounded overflow-hidden mb-2" style="max-height: 160px; overflow-y: auto;">
                                @foreach($this->profesionalesParaSelector as $prof)
                                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                    <span class="small">
                                        {{ $prof->nombre_completo }}
                                        @if($prof->cargo)
                                            <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                                        @endif
                                    </span>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm py-0 px-2 flex-shrink-0"
                                            wire:click="agregarProfesional({{ $prof->id }})">
                                        <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                            @else
                            <p class="text-body-secondary small mb-2">Todos los profesionales del centro ya están asignados.</p>
                            @endif
                        @endif

                        {{-- Picker: modo organización (búsqueda) --}}
                        @if($buscarEnTodo)
                        <div class="mb-2">
                            <input type="text"
                                   class="form-control form-control-sm"
                                   placeholder="Buscar por nombre o apellido…"
                                   wire:model.live.debounce.300ms="busquedaProfesional"
                                   autocomplete="off">
                        </div>
                        @if(mb_strlen(trim($busquedaProfesional)) < 2)
                            <p class="text-body-secondary small mb-2">Escribe al menos 2 caracteres para buscar.</p>
                        @elseif($this->profesionalesParaSelector->isEmpty())
                            <p class="text-body-secondary small mb-2">Sin resultados para «{{ $busquedaProfesional }}».</p>
                        @else
                            <div class="border rounded overflow-hidden mb-2">
                                @foreach($this->profesionalesParaSelector as $prof)
                                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                    <span class="small">
                                        {{ $prof->nombre_completo }}
                                        @if($prof->cargo)
                                            <span class="text-body-secondary">({{ $prof->cargo->nombre }})</span>
                                        @endif
                                    </span>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm py-0 px-2 flex-shrink-0"
                                            wire:click="agregarProfesional({{ $prof->id }})">
                                        <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                            @if($this->profesionalesParaSelector->count() === 15)
                            <p class="text-body-secondary small mb-2">Mostrando los primeros 15 resultados. Ajusta la búsqueda para ver más.</p>
                            @endif
                        @endif
                        @endif

                        {{-- Toggle modo búsqueda --}}
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="act-buscar-todo"
                                   wire:model.live="buscarEnTodo">
                            <label class="form-check-label small text-body-secondary" for="act-buscar-todo">
                                Buscar en toda la organización
                            </label>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm"
                            wire:click="guardar" wire:loading.attr="disabled">
                        <span wire:loading wire:target="guardar"
                              class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        {{ $editandoId ? 'Guardar cambios' : 'Crear actividad' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
