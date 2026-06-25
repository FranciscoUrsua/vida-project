<div class="op-page">

    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <button type="button" class="btn btn-primary btn-sm ms-auto" wire:click="abrirModalAlta">
            <x-heroicon-o-user-plus class="icon-16 me-1" aria-hidden="true"/>
            Añadir profesional
        </button>
    </div>

    @if($avisoAlta)
    <div class="alert alert-info d-flex align-items-start gap-2 mx-3 mt-3" role="alert">
        <x-heroicon-o-information-circle class="icon-20 flex-shrink-0 mt-1" aria-hidden="true"/>
        <span>{{ $avisoAlta }}</span>
    </div>
    @endif

    {{-- Navegación de secciones --}}
    <nav class="nav nav-tabs px-3 mt-3 border-bottom" aria-label="Secciones del equipo">
        <button type="button"
                class="nav-link {{ $tabActiva === 'resumen' ? 'active' : '' }}"
                wire:click="$set('tabActiva', 'resumen')">Resumen</button>
        <button type="button"
                class="nav-link {{ $tabActiva === 'horario' ? 'active' : '' }}"
                wire:click="$set('tabActiva', 'horario')">Perfil horario</button>
        <button type="button"
                class="nav-link {{ $tabActiva === 'suplencias' ? 'active' : '' }}"
                wire:click="$set('tabActiva', 'suplencias')">Suplencias</button>
    </nav>

    {{-- Tab: Resumen --}}
    @if($tabActiva === 'resumen')
    <section class="p-3">
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
                            <td>
                                {{ $prof->nombre_completo }}
                                @if($prof->usuario?->id === auth()->id())
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">Tú</span>
                                @endif
                            </td>
                            <td class="text-body-secondary small">{{ $prof->cargo?->nombre ?? '—' }}</td>
                            <td class="text-end">
                                @if($prof->usuario?->id !== auth()->id())
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        wire:click="iniciarBaja({{ $prof->id }})">
                                    Dar de baja
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @endif

    {{-- Tab: Perfil horario --}}
    @if($tabActiva === 'horario')
    <section class="p-3">
        <div class="op-empty">
            <x-heroicon-o-clock class="op-empty__icon" aria-hidden="true"/>
            <p class="op-empty__text">La gestión de perfiles horarios estará disponible próximamente.</p>
        </div>
    </section>
    @endif

    {{-- Tab: Suplencias --}}
    @if($tabActiva === 'suplencias')
    <section class="p-3">
        <div class="op-empty">
            <x-heroicon-o-arrow-path-rounded-square class="op-empty__icon" aria-hidden="true"/>
            <p class="op-empty__text">La gestión de suplencias estará disponible próximamente.</p>
        </div>
    </section>
    @endif

    {{-- Modal alta de profesional --}}
    @if($modalAltaAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-alta-titulo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-alta-titulo">Añadir profesional</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalAltaAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="nuevoNombre">Nombre completo <span class="text-danger">*</span></label>
                        <input id="nuevoNombre" type="text"
                               class="form-control @error('nuevoNombre') is-invalid @enderror"
                               wire:model="nuevoNombre" maxlength="255" autofocus>
                        @error('nuevoNombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nuevoCargo">Cargo <span class="text-danger">*</span></label>
                        <select id="nuevoCargo"
                                class="form-select @error('nuevoCargo') is-invalid @enderror"
                                wire:model="nuevoCargo">
                            <option value="">Selecciona un cargo…</option>
                            @foreach($this->cargos as $cargo)
                                <option value="{{ $cargo->id }}">{{ $cargo->nombre }}</option>
                            @endforeach
                        </select>
                        @error('nuevoCargo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nuevaFechaIncorporacion">Fecha de incorporación <span class="text-danger">*</span></label>
                        <input id="nuevaFechaIncorporacion" type="date"
                               class="form-control @error('nuevaFechaIncorporacion') is-invalid @enderror"
                               wire:model="nuevaFechaIncorporacion">
                        @error('nuevaFechaIncorporacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalAltaAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm"
                            wire:click="crearProfesional" wire:loading.attr="disabled">
                        <span wire:loading wire:target="crearProfesional"
                              class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        Crear profesional
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal baja de profesional --}}
    @if($modalBajaAbierto)
    <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog"
         aria-labelledby="modal-baja-titulo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-baja-titulo">Dar de baja al profesional</h5>
                    <button type="button" class="btn-close" wire:click="$set('modalBajaAbierto', false)" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($this->casosActivosProfesionalSeleccionado > 0)
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <x-heroicon-o-exclamation-triangle class="icon-20 flex-shrink-0 mt-1" aria-hidden="true"/>
                        <div>
                            <strong>Atención:</strong> Este profesional tiene
                            <strong>{{ $this->casosActivosProfesionalSeleccionado }} casos activos</strong>.
                            Confirma que serán reasignados antes de proceder.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmarBajaConCasos"
                               wire:model="confirmarBajaConCasos">
                        <label class="form-check-label" for="confirmarBajaConCasos">
                            Confirmo que los casos activos serán reasignados.
                        </label>
                    </div>
                    @endif
                    <div class="mb-0">
                        <label class="form-label" for="fechaBaja">Fecha de baja <span class="text-danger">*</span></label>
                        <input id="fechaBaja" type="date"
                               class="form-control @error('fechaBaja') is-invalid @enderror"
                               wire:model="fechaBaja">
                        @error('fechaBaja') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            wire:click="$set('modalBajaAbierto', false)">Cancelar</button>
                    <button type="button" class="btn btn-danger btn-sm"
                            wire:click="confirmarBaja"
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
