<div class="op-page">

    <div class="p-3 border-bottom">
        <p class="text-body-secondary small mb-0">Vacaciones, bajas, reducciones y cambios puntuales</p>
    </div>

    {{-- Formulario de nueva excepción --}}
    <section class="p-3 border-bottom bg-light" aria-labelledby="form-heading">
        <h2 class="h6 fw-semibold mb-3" id="form-heading">Nueva excepción</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="exc-profesional">Profesional <span class="text-danger">*</span></label>
                <select id="exc-profesional"
                        class="form-select form-select-sm @error('form.usuario_id') is-invalid @enderror"
                        wire:model="form.usuario_id">
                    <option value="">Selecciona un profesional…</option>
                    @foreach($this->profesionalesDelCentro as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->profesional?->nombre_completo ?? $user->email }}
                        </option>
                    @endforeach
                </select>
                @error('form.usuario_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="exc-tipo">Tipo de excepción <span class="text-danger">*</span></label>
                <select id="exc-tipo"
                        class="form-select form-select-sm @error('form.tipo') is-invalid @enderror"
                        wire:model="form.tipo">
                    <option value="">Selecciona un tipo…</option>
                    @foreach($this->tiposExcepcion as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('form.tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-2">
                <label class="form-label" for="exc-inicio">Fecha inicio <span class="text-danger">*</span></label>
                <input id="exc-inicio" type="date"
                       class="form-control form-control-sm @error('form.fecha_inicio') is-invalid @enderror"
                       wire:model="form.fecha_inicio">
                @error('form.fecha_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-2">
                <label class="form-label" for="exc-fin">Fecha fin</label>
                <input id="exc-fin" type="date"
                       class="form-control form-control-sm @error('form.fecha_fin') is-invalid @enderror"
                       wire:model="form.fecha_fin">
                @error('form.fecha_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="exc-notas">Notas</label>
                <textarea id="exc-notas"
                          class="form-control form-control-sm @error('form.notas') is-invalid @enderror"
                          wire:model="form.notas"
                          rows="2"
                          maxlength="500"
                          placeholder="Número de parte de baja, referencia, etc."></textarea>
                @error('form.notas') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <button type="button"
                        class="btn btn-primary btn-sm"
                        wire:click="guardar"
                        wire:loading.attr="disabled">
                    <span wire:loading wire:target="guardar"
                          class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    Registrar excepción
                </button>
            </div>
        </div>
    </section>

    {{-- Lista de excepciones activas y próximas --}}
    <section class="p-3" aria-labelledby="lista-heading">
        <h2 class="h6 fw-semibold mb-3" id="lista-heading">Excepciones activas y próximas</h2>

        @if($this->excepcionesActivas->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-calendar-check class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay excepciones activas ni programadas.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Profesional</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Notas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->excepcionesActivas as $exc)
                    @php
                        $chipClass = match($exc->tipo->value) {
                            'baja_medica'       => 'bg-danger-subtle text-danger-emphasis',
                            'vacaciones'        => 'bg-success-subtle text-success-emphasis',
                            'reduccion_jornada' => 'bg-warning-subtle text-warning-emphasis',
                            'dia_libre'         => 'bg-info-subtle text-info-emphasis',
                            default             => 'bg-secondary-subtle text-secondary-emphasis',
                        };
                    @endphp
                    <tr>
                        <td class="fw-medium">
                            {{ $exc->usuario?->profesional?->nombre_completo ?? $exc->usuario?->email ?? '—' }}
                        </td>
                        <td><span class="badge {{ $chipClass }}">{{ $exc->tipo->label() }}</span></td>
                        <td>{{ $exc->fecha_inicio->format('d/m/Y') }}</td>
                        <td>{{ $exc->fecha_fin ? $exc->fecha_fin->format('d/m/Y') : '—' }}</td>
                        <td class="text-body-secondary">{{ Str::limit($exc->notas ?? '—', 40) }}</td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 text-danger"
                                    wire:click="eliminar({{ $exc->id }})"
                                    wire:confirm="¿Eliminar esta excepción? Los slots y cuadrante no se restaurarán automáticamente.">
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
