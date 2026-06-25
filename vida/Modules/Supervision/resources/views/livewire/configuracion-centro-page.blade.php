<div class="op-page">

    <form wire:submit="guardar">

        {{-- Identificación del centro --}}
        <section class="p-3 border-bottom" aria-labelledby="identidad-heading">
            <h2 class="h6 fw-semibold mb-3" id="identidad-heading">Identificación del centro</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombreCorto">Nombre corto</label>
                    <input id="nombreCorto" type="text" class="form-control" wire:model="nombreCorto" maxlength="50">
                    @error('nombreCorto') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </section>

        {{-- Horario y agenda --}}
        <section class="p-3 border-bottom" aria-labelledby="agenda-heading">
            <h2 class="h6 fw-semibold mb-3" id="agenda-heading">Horario y agenda</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="modoAgenda">Modo de agenda</label>
                    <select id="modoAgenda" class="form-select" wire:model.live="modoAgenda">
                        <option value="basico">Básico</option>
                        <option value="estandar">Estándar</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                    @if($mostrarAdvertenciaModoAgenda)
                    <div class="alert alert-warning d-flex align-items-start gap-2 mt-2 mb-0 py-2">
                        <x-heroicon-o-exclamation-triangle class="icon-16 flex-shrink-0 mt-1" aria-hidden="true"/>
                        <small>Este cambio afectará a la interfaz de todos los profesionales del centro.</small>
                    </div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="umbralRatio">Umbral ratio personas/profesional</label>
                    <input id="umbralRatio" type="number" step="0.1" min="1" max="100" class="form-control" wire:model="umbralRatio">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="umbralEsperaDias">Umbral espera media (días)</label>
                    <input id="umbralEsperaDias" type="number" min="1" max="365" class="form-control" wire:model="umbralEsperaDias">
                </div>
            </div>
        </section>

        @if($this->tienePlazas)
        {{-- Plazas --}}
        <section class="p-3 border-bottom" aria-labelledby="plazas-heading">
            <h2 class="h6 fw-semibold mb-3" id="plazas-heading">Plazas</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="capacidadMaxima">Capacidad máxima declarada</label>
                    <input id="capacidadMaxima" type="number" min="0" class="form-control">
                    <div class="form-text">Informativo. No impide crear más plazas.</div>
                </div>
            </div>
        </section>
        @endif

        <div class="d-flex gap-2 p-3 border-bottom">
            <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
        </div>

    </form>

    {{-- Salas del centro --}}
    <section class="p-3" aria-labelledby="salas-heading">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h2 class="h6 fw-semibold mb-0" id="salas-heading">Salas del centro</h2>
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto"
                    wire:click="abrirModalSala">
                <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
                Nueva sala
            </button>
        </div>

        @if($this->salas->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-building-office class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay salas registradas en este centro.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Capacidad</th>
                            <th>Accesible</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->salas as $sala)
                        <tr>
                            <td class="fw-medium">{{ $sala->nombre }}</td>
                            <td class="text-body-secondary small">{{ $sala->capacidad ?? '—' }}</td>
                            <td>
                                @if($sala->accesible)
                                    <x-heroicon-s-check-circle class="icon-16 text-success" aria-label="Sí"/>
                                @else
                                    <span class="text-body-secondary small">No</span>
                                @endif
                            </td>
                            <td>
                                @if($sala->activa)
                                    <span class="badge bg-success-subtle text-success-emphasis">Activa</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 me-3"
                                        wire:click="abrirEdicionSala({{ $sala->id }})">
                                    Editar
                                </button>
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 text-danger"
                                        wire:click="eliminarSala({{ $sala->id }})"
                                        wire:confirm="¿Eliminar la sala «{{ $sala->nombre }}»? Esta acción no puede deshacerse.">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Modal sala (alta y edición) --}}
    @if($modalSalaAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-sala-titulo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-sala-titulo">
                        {{ $editandoSalaId ? 'Editar sala' : 'Nueva sala' }}
                    </h5>
                    <button type="button" class="btn-close"
                            wire:click="$set('modalSalaAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label" for="sala-nombre">Nombre <span class="text-danger">*</span></label>
                        <input id="sala-nombre" type="text"
                               class="form-control @error('salaNombre') is-invalid @enderror"
                               wire:model="salaNombre" maxlength="100" autofocus>
                        @error('salaNombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="sala-capacidad">Capacidad (personas)</label>
                            <input id="sala-capacidad" type="number" min="1"
                                   class="form-control @error('salaCapacidad') is-invalid @enderror"
                                   wire:model="salaCapacidad" placeholder="Sin límite">
                            @error('salaCapacidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6 d-flex flex-column justify-content-end gap-2 pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sala-accesible"
                                       wire:model="salaAccesible">
                                <label class="form-check-label" for="sala-accesible">Accesible para movilidad reducida</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sala-activa"
                                       wire:model="salaActiva">
                                <label class="form-check-label" for="sala-activa">Sala activa</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sala-descripcion">Descripción</label>
                        <textarea id="sala-descripcion" class="form-control @error('salaDescripcion') is-invalid @enderror"
                                  wire:model="salaDescripcion" rows="2" maxlength="500"
                                  placeholder="Uso habitual, equipamiento…"></textarea>
                        @error('salaDescripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="sala-notas">Notas internas</label>
                        <textarea id="sala-notas" class="form-control @error('salaNotes') is-invalid @enderror"
                                  wire:model="salaNotes" rows="2" maxlength="500"></textarea>
                        @error('salaNotes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalSalaAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm"
                            wire:click="guardarSala" wire:loading.attr="disabled">
                        <span wire:loading wire:target="guardarSala"
                              class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        {{ $editandoSalaId ? 'Guardar cambios' : 'Crear sala' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
