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
                            <td class="fw-medium">{{ $actividad->nombre }}</td>
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

    {{-- Modal nueva actividad --}}
    @if($modalAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-actividad-titulo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-actividad-titulo">Nueva actividad</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label" for="act-nombre">Nombre <span class="text-danger">*</span></label>
                        <input id="act-nombre" type="text" class="form-control @error('nombre') is-invalid @enderror"
                               wire:model="nombre" maxlength="200" autofocus>
                        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
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

                    <div class="mb-3">
                        <label class="form-label" for="act-modo">Modo de acceso <span class="text-danger">*</span></label>
                        <select id="act-modo" class="form-select @error('modoAcceso') is-invalid @enderror"
                                wire:model.live="modoAcceso">
                            <option value="libre">Libre</option>
                            <option value="prescripcion">Prescripción</option>
                            <option value="mixta">Mixta</option>
                        </select>
                        @error('modoAcceso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label" for="act-aforo">Aforo total</label>
                            <input id="act-aforo" type="number" min="1" class="form-control @error('aforoTotal') is-invalid @enderror"
                                   wire:model="aforoTotal" placeholder="Sin límite">
                            @error('aforoTotal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @if($modoAcceso !== 'libre')
                        <div class="col">
                            <label class="form-label" for="act-aforo-presc">Aforo prescripción</label>
                            <input id="act-aforo-presc" type="number" min="0" class="form-control @error('aforoPresc') is-invalid @enderror"
                                   wire:model="aforoPresc">
                            @error('aforoPresc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="act-fecha">Fecha de alta <span class="text-danger">*</span></label>
                        <input id="act-fecha" type="date" class="form-control @error('fechaAlta') is-invalid @enderror"
                               wire:model="fechaAlta">
                        @error('fechaAlta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="act-inscripcion"
                               wire:model="requiereInscripcion">
                        <label class="form-check-label" for="act-inscripcion">
                            Requiere inscripción previa al centro
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm"
                            wire:click="crear" wire:loading.attr="disabled">
                        <span wire:loading wire:target="crear"
                              class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        Crear actividad
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
