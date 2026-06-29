<div class="op-page">

    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <p class="text-body-secondary small mb-0">Reuniones, formaciones y sesiones de equipo</p>
        <button type="button"
                class="btn btn-primary btn-sm ms-auto"
                wire:click="$toggle('mostrarFormulario')">
            <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
            Nuevo evento
        </button>
    </div>

    {{-- Aviso conflicto de espacio --}}
    @if($hayConflictoEspacio)
    <div class="alert alert-warning mx-3 mt-3 mb-0 d-flex align-items-center gap-2 py-2" role="alert">
        <x-heroicon-o-exclamation-triangle class="icon-16 flex-shrink-0" aria-hidden="true"/>
        El espacio seleccionado ya tiene otro evento en ese horario. El evento se ha creado igualmente.
        <button type="button" class="btn-close btn-sm ms-auto"
                wire:click="$set('hayConflictoEspacio', false)" aria-label="Cerrar aviso"></button>
    </div>
    @endif

    {{-- Formulario de nuevo evento --}}
    @if($mostrarFormulario)
    <section class="p-3 border-bottom bg-light" aria-labelledby="form-evento-heading">
        <h2 class="h6 fw-semibold mb-3" id="form-evento-heading">Nuevo evento</h2>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="ev-nombre">Nombre <span class="text-danger">*</span></label>
                <input id="ev-nombre" type="text"
                       class="form-control form-control-sm @error('form.nombre') is-invalid @enderror"
                       wire:model="form.nombre" maxlength="200">
                @error('form.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ev-tipo">Tipo <span class="text-danger">*</span></label>
                <select id="ev-tipo"
                        class="form-select form-select-sm @error('form.tipo_evento') is-invalid @enderror"
                        wire:model="form.tipo_evento">
                    <option value="">Selecciona…</option>
                    <option value="sesion_interna">Sesión interna</option>
                    <option value="actividad_colectiva">Actividad colectiva</option>
                    <option value="coordinacion">Coordinación</option>
                </select>
                @error('form.tipo_evento') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ev-espacio">Espacio</label>
                <select id="ev-espacio"
                        class="form-select form-select-sm @error('form.espacio_id') is-invalid @enderror"
                        wire:model="form.espacio_id">
                    <option value="">Sin espacio</option>
                    @foreach($this->espaciosDelCentro as $espacio)
                        <option value="{{ $espacio->id }}">{{ $espacio->nombre }}</option>
                    @endforeach
                </select>
                @error('form.espacio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ev-fecha">Fecha <span class="text-danger">*</span></label>
                <input id="ev-fecha" type="date"
                       class="form-control form-control-sm @error('form.fecha') is-invalid @enderror"
                       wire:model="form.fecha">
                @error('form.fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ev-inicio">Hora inicio <span class="text-danger">*</span></label>
                <input id="ev-inicio" type="time"
                       class="form-control form-control-sm @error('form.hora_inicio') is-invalid @enderror"
                       wire:model="form.hora_inicio">
                @error('form.hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label" for="ev-duracion">Duración (min) <span class="text-danger">*</span></label>
                <input id="ev-duracion" type="number" min="5" max="480"
                       class="form-control form-control-sm @error('form.duracion_minutos') is-invalid @enderror"
                       wire:model="form.duracion_minutos" placeholder="60">
                @error('form.duracion_minutos') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label class="form-label">Profesionales convocados</label>
                <div class="d-flex flex-wrap gap-2">
                    @forelse($this->profesionalesDelCentro as $prof)
                    @if($prof->usuario)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               id="prof-{{ $prof->usuario->id }}"
                               value="{{ $prof->usuario->id }}"
                               wire:model="form.profesionales_ids">
                        <label class="form-check-label small" for="prof-{{ $prof->usuario->id }}">
                            {{ $prof->nombre_completo }}
                        </label>
                    </div>
                    @endif
                    @empty
                    <p class="text-body-secondary small mb-0">No hay profesionales en tu unidad organizativa.</p>
                    @endforelse
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="button"
                        class="btn btn-primary btn-sm"
                        wire:click="crear"
                        wire:loading.attr="disabled">
                    <span wire:loading wire:target="crear"
                          class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    Crear evento
                </button>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        wire:click="$set('mostrarFormulario', false)">
                    Cancelar
                </button>
            </div>
        </div>
    </section>
    @endif

    {{-- Lista de eventos próximos --}}
    <section class="p-3" aria-labelledby="eventos-heading">
        <h2 class="h6 fw-semibold mb-3" id="eventos-heading">Eventos próximos</h2>

        @if($this->eventosProximos->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-users class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay eventos próximos programados.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Espacio</th>
                        <th>Convocados</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->eventosProximos as $evento)
                    @php
                        $tipoChip = match($evento->tipo_evento) {
                            'sesion_interna'     => 'bg-primary-subtle text-primary-emphasis',
                            'actividad_colectiva'=> 'bg-success-subtle text-success-emphasis',
                            'coordinacion'       => 'bg-warning-subtle text-warning-emphasis',
                            default              => 'bg-secondary-subtle text-secondary-emphasis',
                        };
                        $tipoLabel = match($evento->tipo_evento) {
                            'sesion_interna'     => 'Sesión interna',
                            'actividad_colectiva'=> 'Actividad colectiva',
                            'coordinacion'       => 'Coordinación',
                            default              => $evento->tipo_evento,
                        };
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $evento->fecha->format('d/m/Y') }}</td>
                        <td>{{ substr($evento->hora_inicio, 0, 5) }} — {{ substr($evento->hora_fin, 0, 5) }}</td>
                        <td class="fw-medium">{{ $evento->titulo }}</td>
                        <td><span class="badge {{ $tipoChip }}">{{ $tipoLabel }}</span></td>
                        <td class="text-body-secondary">{{ $evento->espacio?->nombre ?? '—' }}</td>
                        <td class="text-body-secondary">
                            @if($evento->profesionales->isNotEmpty())
                                {{ $evento->profesionales->map(fn($u) => $u->profesional?->nombre_completo ?? $u->email)->join(', ') }}
                            @else
                                <span class="text-body-tertiary">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 text-danger"
                                    wire:click="eliminar({{ $evento->id }})"
                                    wire:confirm="¿Eliminar el evento «{{ $evento->titulo }}»? Los slots bloqueados quedarán libres.">
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

</div>
