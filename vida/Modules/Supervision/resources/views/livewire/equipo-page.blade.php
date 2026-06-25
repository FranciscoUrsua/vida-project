<div class="op-page">

    <div class="op-toolbar">
        <h2 class="h5 mb-0 fw-semibold">Mi equipo</h2>
        <button type="button" class="btn btn-primary btn-sm ms-auto" wire:click="abrirModalAlta">
            <x-heroicon-o-user-plus class="icon-16 me-1" aria-hidden="true"/>
            Añadir profesional
        </button>
    </div>

    @if($avisoAlta)
    <div class="alert alert-info d-flex align-items-start gap-2 mx-3 mt-2" role="alert">
        <x-heroicon-o-information-circle class="icon-20 flex-shrink-0 mt-1" aria-hidden="true"/>
        <span>{{ $avisoAlta }}</span>
    </div>
    @endif

    {{-- Navegación de ficha de profesional --}}
    <nav class="nav nav-tabs px-3 border-bottom" aria-label="Secciones de la ficha del profesional">
        <a class="nav-link active" href="#">Resumen</a>
        <a class="nav-link" href="#">Perfil horario</a>
        <a class="nav-link" href="#">Suplencias</a>
    </nav>

    <section class="op-section">
        @if($this->profesionales->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-users class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay profesionales en este centro.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profesional</th>
                            <th>Cargo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->profesionales as $prof)
                        <tr>
                            <td>{{ $prof->nombre_completo }}</td>
                            <td>{{ $prof->cargo?->nombre ?? '—' }}</td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        wire:click="iniciarBaja({{ $prof->id }})">
                                    Dar de baja
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Modal alta de profesional --}}
    @if($modalAltaAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir profesional</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalAltaAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="nuevoNombre">Nombre completo</label>
                        <input id="nuevoNombre" type="text" class="form-control" wire:model="nuevoNombre" required>
                        @error('nuevoNombre') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nuevaFechaIncorporacion">Fecha de incorporación</label>
                        <input id="nuevaFechaIncorporacion" type="date" class="form-control" wire:model="nuevaFechaIncorporacion" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('modalAltaAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="crearProfesional">Crear profesional</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal baja de profesional --}}
    @if($modalBajaAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title">Dar de baja al profesional</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalBajaAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($this->casosActivosProfesionalSeleccionado > 0)
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <x-heroicon-o-exclamation-triangle class="icon-20 flex-shrink-0 mt-1" aria-hidden="true"/>
                        <div>
                            <strong>Atención:</strong> Este profesional tiene <strong>{{ $this->casosActivosProfesionalSeleccionado }} casos activos</strong>.
                            Confirma que serán reasignados antes de proceder.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmarBajaConCasos" wire:model="confirmarBajaConCasos">
                        <label class="form-check-label" for="confirmarBajaConCasos">
                            Confirmo que los casos activos serán reasignados.
                        </label>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label" for="fechaBaja">Fecha de baja</label>
                        <input id="fechaBaja" type="date" class="form-control" wire:model="fechaBaja" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('modalBajaAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm" wire:click="confirmarBaja"
                            @if($this->casosActivosProfesionalSeleccionado > 0 && !$confirmarBajaConCasos) disabled @endif>
                        Confirmar baja
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

</div>
