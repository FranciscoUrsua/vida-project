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
        @include('agenda::livewire.supervisor.partials.form-evento', ['prefix' => 'form', 'accion' => 'crear', 'labelBoton' => 'Crear evento'])
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
                        $esEditable = $evento->fecha->greaterThanOrEqualTo(today());
                        $tipoChip = match($evento->tipo_evento) {
                            'sesion_interna'      => 'bg-primary-subtle text-primary-emphasis',
                            'actividad_colectiva' => 'bg-success-subtle text-success-emphasis',
                            'coordinacion'        => 'bg-warning-subtle text-warning-emphasis',
                            default               => 'bg-secondary-subtle text-secondary-emphasis',
                        };
                        $tipoLabel = match($evento->tipo_evento) {
                            'sesion_interna'      => 'Sesión interna',
                            'actividad_colectiva' => 'Actividad colectiva',
                            'coordinacion'        => 'Coordinación',
                            default               => $evento->tipo_evento,
                        };
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $evento->fecha->format('d/m/Y') }}</td>
                        <td>{{ substr($evento->hora_inicio, 0, 5) }} — {{ substr($evento->hora_fin, 0, 5) }}</td>
                        <td class="fw-medium">
                            @if($esEditable)
                                <button type="button"
                                        class="btn btn-link p-0 fw-medium text-start text-body-emphasis"
                                        wire:click="abrirEdicion({{ $evento->id }})">
                                    {{ $evento->titulo }}
                                </button>
                            @else
                                {{ $evento->titulo }}
                            @endif
                        </td>
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

    {{-- Modal de edición --}}
    @if($mostrarModalEdicion)
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-edicion-titulo">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-edicion-titulo">Editar evento</h5>
                    <button type="button" class="btn-close" wire:click="cerrarEdicion" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @include('agenda::livewire.supervisor.partials.form-evento', ['prefix' => 'formEdicion', 'accion' => 'actualizar', 'labelBoton' => 'Guardar cambios'])
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
