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
                            <th></th>
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
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        wire:click="abrirSesiones({{ $actividad->id }})">
                                    <x-heroicon-o-calendar-days class="icon-14 me-1" aria-hidden="true"/>
                                    Sesiones
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ================================================================ --}}
    {{-- Modal actividad (alta y edición)                                 --}}
    {{-- ================================================================ --}}
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

                    {{-- Picker profesionales actividad --}}
                    @include('supervision::livewire.partials.picker-profesionales', [
                        'prefijo'        => 'act',
                        'asignados'      => $this->profesionalesAsignados,
                        'paraSelector'   => $this->profesionalesParaSelector,
                        'buscarEnTodo'   => $buscarEnTodo,
                        'busqueda'       => $busquedaProfesional,
                        'agregarAction'  => 'agregarProfesional',
                        'quitarAction'   => 'quitarProfesional',
                        'toggleId'       => 'act-buscar-todo',
                        'toggleModel'    => 'buscarEnTodo',
                        'busquedaModel'  => 'busquedaProfesional',
                        'errorKey'       => 'profesionalesIds',
                        'requerido'      => true,
                    ])

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

    {{-- ================================================================ --}}
    {{-- Modal sesiones                                                    --}}
    {{-- ================================================================ --}}
    @if($modalSesionesAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-sesiones-titulo">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    @if($sesionesModo === 'formulario')
                    <button type="button" class="btn btn-link p-0 me-2 text-body"
                            wire:click="volverALista" aria-label="Volver al listado">
                        <x-heroicon-o-arrow-left class="icon-20" aria-hidden="true"/>
                    </button>
                    @endif
                    <h5 class="modal-title" id="modal-sesiones-titulo">
                        @if($sesionesModo === 'lista')
                            Sesiones — {{ $this->actividadParaSesiones?->nombre }}
                        @else
                            {{ $editandoSesionId ? 'Editar sesión' : 'Nueva sesión' }}
                        @endif
                    </h5>
                    <button type="button" class="btn-close ms-auto"
                            wire:click="$set('modalSesionesAbierto', false)" aria-label="Cerrar"></button>
                </div>

                {{-- Vista: lista de sesiones --}}
                @if($sesionesModo === 'lista')
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom d-flex justify-content-end">
                        <button type="button" class="btn btn-primary btn-sm"
                                wire:click="nuevaSesion">
                            <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
                            Nueva sesión
                        </button>
                    </div>

                    @if($this->sesiones->isEmpty())
                        <div class="op-empty">
                            <x-heroicon-o-calendar class="op-empty__icon" aria-hidden="true"/>
                            <p class="op-empty__text">No hay sesiones programadas para esta actividad.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Sala</th>
                                        <th>Profesionales</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->sesiones as $sesion)
                                    @php
                                        $estados = ['programada' => ['bg-primary-subtle text-primary-emphasis', 'Programada'], 'celebrada' => ['bg-success-subtle text-success-emphasis', 'Celebrada'], 'cancelada' => ['bg-danger-subtle text-danger-emphasis', 'Cancelada']];
                                        [$badgeClass, $badgeLabel] = $estados[$sesion->estado] ?? ['bg-secondary-subtle text-secondary-emphasis', $sesion->estado];
                                    @endphp
                                    <tr>
                                        <td class="fw-medium small">{{ $sesion->fecha->format('d/m/Y') }}</td>
                                        <td class="small text-body-secondary">
                                            {{ substr($sesion->hora_inicio, 0, 5) }}
                                            @if($sesion->hora_fin)
                                                — {{ substr($sesion->hora_fin, 0, 5) }}
                                            @endif
                                        </td>
                                        <td class="small text-body-secondary">{{ $sesion->sala?->nombre ?? '—' }}</td>
                                        <td class="small text-body-secondary">
                                            @if($sesion->profesionales->isNotEmpty())
                                                {{ $sesion->profesionales->map(fn($p) => $p->nombre_completo)->join(', ') }}
                                            @else
                                                <span class="text-body-tertiary">—</span>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 me-3"
                                                    wire:click="editarSesion({{ $sesion->id }})">
                                                Editar
                                            </button>
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 text-danger"
                                                    wire:click="eliminarSesion({{ $sesion->id }})"
                                                    wire:confirm="¿Eliminar la sesión del {{ $sesion->fecha->format('d/m/Y') }}?">
                                                Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalSesionesAbierto', false)">Cerrar</button>
                </div>
                @endif

                {{-- Vista: formulario de sesión --}}
                @if($sesionesModo === 'formulario')
                <div class="modal-body">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label" for="ses-fecha">Fecha <span class="text-danger">*</span></label>
                            <input id="ses-fecha" type="date"
                                   class="form-control @error('sesionFecha') is-invalid @enderror"
                                   wire:model="sesionFecha">
                            @error('sesionFecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="ses-inicio">Hora inicio <span class="text-danger">*</span></label>
                            <input id="ses-inicio" type="time"
                                   class="form-control @error('sesionHoraInicio') is-invalid @enderror"
                                   wire:model="sesionHoraInicio">
                            @error('sesionHoraInicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="ses-fin">Hora fin</label>
                            <input id="ses-fin" type="time"
                                   class="form-control @error('sesionHoraFin') is-invalid @enderror"
                                   wire:model="sesionHoraFin">
                            @error('sesionHoraFin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="ses-sala">Sala</label>
                            <select id="ses-sala"
                                    class="form-select @error('sesionSalaId') is-invalid @enderror"
                                    wire:model="sesionSalaId">
                                <option value="">Sin sala asignada</option>
                                @foreach($this->salasDelCentro as $sala)
                                    <option value="{{ $sala->id }}">
                                        {{ $sala->nombre }}{{ $sala->capacidad ? ' (cap. '.$sala->capacidad.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if($this->salasDelCentro->isEmpty())
                                <div class="form-text">
                                    <a href="{{ route('supervision.configuracion') }}" class="text-decoration-none">
                                        Configura las salas del centro
                                    </a> para poder asignarlas a las sesiones.
                                </div>
                            @endif
                            @error('sesionSalaId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="ses-estado">Estado <span class="text-danger">*</span></label>
                            <select id="ses-estado"
                                    class="form-select @error('sesionEstado') is-invalid @enderror"
                                    wire:model="sesionEstado">
                                <option value="programada">Programada</option>
                                <option value="celebrada">Celebrada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                            @error('sesionEstado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if($this->actividadParaSesiones?->modo_acceso !== 'libre')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="ses-aforo">Aforo total (sobreescribe el de la actividad)</label>
                            <input id="ses-aforo" type="number" min="1"
                                   class="form-control @error('sesionAforoTotal') is-invalid @enderror"
                                   wire:model="sesionAforoTotal" placeholder="Hereda de la actividad">
                            @error('sesionAforoTotal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="ses-aforo-presc">Aforo prescripción</label>
                            <input id="ses-aforo-presc" type="number" min="0"
                                   class="form-control @error('sesionAforoPresc') is-invalid @enderror"
                                   wire:model="sesionAforoPresc" placeholder="Hereda de la actividad">
                            @error('sesionAforoPresc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label" for="ses-notas">Notas</label>
                        <textarea id="ses-notas" class="form-control @error('sesionNotas') is-invalid @enderror"
                                  wire:model="sesionNotas" rows="2" maxlength="1000"></textarea>
                        @error('sesionNotas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Picker profesionales sesión --}}
                    @include('supervision::livewire.partials.picker-profesionales', [
                        'prefijo'        => 'ses',
                        'asignados'      => $this->sesionProfesionalesAsignados,
                        'paraSelector'   => $this->sesionProfesionalesParaSelector,
                        'buscarEnTodo'   => $sesionBuscarEnTodo,
                        'busqueda'       => $sesionBusquedaProfesional,
                        'agregarAction'  => 'agregarSesionProfesional',
                        'quitarAction'   => 'quitarSesionProfesional',
                        'toggleId'       => 'ses-buscar-todo',
                        'toggleModel'    => 'sesionBuscarEnTodo',
                        'busquedaModel'  => 'sesionBusquedaProfesional',
                        'errorKey'       => null,
                        'requerido'      => false,
                    ])

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="volverALista">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm"
                            wire:click="guardarSesion" wire:loading.attr="disabled">
                        <span wire:loading wire:target="guardarSesion"
                              class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        {{ $editandoSesionId ? 'Guardar cambios' : 'Crear sesión' }}
                    </button>
                </div>
                @endif

            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
