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

        {{-- Solicitudes de rol --}}
        @if(in_array($tabActiva, ['todas', 'roles']))
            @if($this->solicitudesRol->isEmpty())
                <div class="op-empty">
                    <x-heroicon-o-check-badge class="op-empty__icon" aria-hidden="true"/>
                    <p class="op-empty__text">No hay solicitudes de rol pendientes.</p>
                </div>
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
