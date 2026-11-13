<div class="op-page">

    {{-- Pestañas --}}
    <section class="p-3">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tabActiva === 'todas' ? 'active' : '' }}"
                        wire:click="$set('tabActiva', 'todas')"
                        role="tab">Todas</button>
            </li>
            @if($this->tieneColectivosProtegidos)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tabActiva === 'accesos' ? 'active' : '' }}"
                        wire:click="$set('tabActiva', 'accesos')"
                        role="tab">Accesos a expedientes</button>
            </li>
            @endif
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $tabActiva === 'roles' ? 'active' : '' }}"
                        wire:click="$set('tabActiva', 'roles')"
                        role="tab">Asignaciones de rol</button>
            </li>
        </ul>

        {{-- Solicitudes de acceso a personas protegidas (RN-18) --}}
        @if(in_array($tabActiva, ['todas', 'accesos']) && $this->tieneColectivosProtegidos)
            @if($this->accesosPendientes->isEmpty())
                @if($tabActiva === 'accesos')
                    <x-op.empty icono="shield-check">No hay solicitudes de acceso pendientes.</x-op.empty>
                @endif
            @else
                <div class="list-group list-group-flush border rounded mb-3">
                    @foreach($this->accesosPendientes as $acceso)
                    <div class="list-group-item py-3" wire:key="acceso-{{ $acceso->id }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $acceso->usuario?->nombreVisible() ?? '—' }}</strong>
                                <span class="text-body-secondary">solicita acceso a</span>
                                <strong>{{ $this->personaDe($acceso) }}</strong>
                            </div>
                            <span class="text-body-secondary small flex-shrink-0">{{ $acceso->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="small mb-2 mt-1">{{ $acceso->justificacion }}</p>

                        @if($accesoActivoId === $acceso->id)
                            @if($accionAcceso === 'aprobar')
                                <div class="d-flex flex-wrap align-items-end gap-2">
                                    <div>
                                        <label for="caducidad-acceso" class="form-label small mb-1">Acceso válido hasta</label>
                                        <input id="caducidad-acceso" type="date" class="form-control form-control-sm" wire:model="caducidadAcceso">
                                    </div>
                                    <button type="button" class="btn btn-sm btn-success" wire:click="resolverAcceso">Confirmar aprobación</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelarAcceso">Cancelar</button>
                                </div>
                            @else
                                <div class="d-flex flex-wrap gap-2">
                                    <label for="motivo-acceso" class="visually-hidden">Motivo de la denegación</label>
                                    <input id="motivo-acceso" type="text" class="form-control form-control-sm flex-grow-1 w-auto"
                                           wire:model="motivoAcceso" placeholder="Motivo de la denegación (obligatorio)">
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="resolverAcceso">Confirmar denegación</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelarAcceso">Cancelar</button>
                                </div>
                            @endif
                            @error('accesoActivo')
                                <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                            @enderror
                        @else
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="iniciarAcceso({{ $acceso->id }}, 'aprobar')">Aprobar</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="iniciarAcceso({{ $acceso->id }}, 'denegar')">Denegar</button>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Solicitudes de rol --}}
        @if(in_array($tabActiva, ['todas', 'roles']))
            @if($this->solicitudesRol->isEmpty())
                <x-op.empty icono="check-badge">No hay solicitudes de rol pendientes.</x-op.empty>
            @else
                <div class="list-group list-group-flush border rounded mb-3">
                    @foreach($this->solicitudesRol as $solicitud)
                    <div class="list-group-item py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>{{ $solicitud->usuario?->profesional?->nombre_completo ?? $solicitud->usuario?->email }}</strong>
                                <span class="badge bg-secondary ms-2">{{ $solicitud->rol?->name }}</span>
                            </div>
                            <span class="text-body-secondary small">{{ $solicitud->created_at?->diffForHumans() }}</span>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button"
                                    class="btn btn-sm btn-outline-success"
                                    wire:click="aprobarSolicitud({{ $solicitud->id }})">
                                Aprobar
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    wire:click="$set('solicitudActivaId', {{ $solicitud->id }})">
                                Denegar
                            </button>
                        </div>

                        @if($solicitudActivaId === $solicitud->id)
                        <div class="mt-2 d-flex gap-2">
                            <input type="text" class="form-control form-control-sm"
                                   wire:model="motivoDenegacion"
                                   placeholder="Motivo de la denegación (obligatorio)">
                            <button type="button"
                                    class="btn btn-sm btn-danger flex-shrink-0"
                                    wire:click="denegarSolicitud({{ $solicitud->id }}, '{{ addslashes($motivoDenegacion) }}')">
                                Confirmar denegación
                            </button>
                        </div>
                        @error('motivoDenegacion')
                            <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                        @enderror
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        @endif

    </section>

</div>
