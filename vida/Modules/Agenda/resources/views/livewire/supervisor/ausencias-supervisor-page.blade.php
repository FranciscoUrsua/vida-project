<div class="op-page">

    {{-- Toolbar --}}
    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <span class="text-body-secondary small">{{ now()->translatedFormat('l, d \d\e F') }}</span>
        <a href="{{ route('agenda.supervisor.excepciones') }}"
           class="btn btn-primary btn-sm ms-auto">
            <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
            Registrar ausencia
        </a>
    </div>

    <div class="row g-0 flex-grow-1">

        {{-- Contenido principal --}}
        <div class="col p-3">

            {{-- Sección: ausencias sobrevenidas --}}
            <section class="mb-4" aria-labelledby="ausencias-heading">
                <h2 class="h6 fw-semibold mb-3" id="ausencias-heading">Ausencias sobrevenidas con citas pendientes</h2>

                @if($this->ausenciasHoy->isEmpty())
                    <div class="op-empty op-empty--compact">
                        <x-heroicon-o-check-circle class="op-empty__icon" aria-hidden="true"/>
                        <p class="op-empty__text">No hay ausencias registradas para hoy.</p>
                    </div>
                @else
                    @foreach($this->ausenciasHoy as $excepcion)
                    @php
                        $citasProf = $this->citasPendientes->where('profesional_id', $excepcion->usuario_id);
                        $todasGestionadas = $citasProf->every(fn($c) => str_contains($c->motivo_cancelacion ?? '', 'descartada') || $c->reasignacion !== null);
                    @endphp
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-danger-subtle d-flex align-items-center gap-2">
                            <x-heroicon-s-user-circle class="icon-20 text-danger" aria-hidden="true"/>
                            <span class="fw-semibold">
                                {{ $excepcion->usuario->profesional?->nombre_completo ?? $excepcion->usuario->email }}
                            </span>
                            <span class="badge bg-danger ms-1">{{ $excepcion->tipo->label() }}</span>
                            @if($todasGestionadas)
                                <span class="badge bg-success ms-auto">Todo gestionado</span>
                            @else
                                <span class="badge bg-warning text-dark ms-auto">
                                    {{ $citasProf->filter(fn($c) => ! str_contains($c->motivo_cancelacion ?? '', 'descartada') && $c->reasignacion === null)->count() }} pendientes
                                </span>
                            @endif
                        </div>

                        @if($citasProf->isEmpty())
                        <div class="card-body py-2">
                            <p class="text-body-secondary small mb-0">Sin citas programadas para hoy.</p>
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Ciudadano/a</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($citasProf as $cita)
                                    @php
                                        $reasignada = $cita->reasignacion !== null;
                                        $descartada = str_contains($cita->motivo_cancelacion ?? '', 'descartada');
                                    @endphp
                                    <tr>
                                        <td class="fw-medium">{{ substr($cita->hora_inicio, 0, 5) }}</td>
                                        <td>{{ $cita->ciudadano?->nombre_completo ?? '—' }}</td>
                                        <td class="text-body-secondary">{{ $cita->tipoSlot?->nombre ?? '—' }}</td>
                                        <td>
                                            @if($reasignada)
                                                <span class="badge bg-success-subtle text-success-emphasis">Reasignada</span>
                                            @elseif($descartada)
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Descartada</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning-emphasis">Pendiente</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(! $reasignada && ! $descartada)
                                            <button type="button"
                                                    class="btn btn-primary btn-sm me-2"
                                                    wire:click="abrirReasignacion({{ $cita->id }})">
                                                Reasignar
                                            </button>
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    wire:click="descartar({{ $cita->id }})"
                                                    wire:confirm="¿Descartar esta cita sin reasignarla?">
                                                Descartar
                                            </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @endif
            </section>

            {{-- Sección: no-shows ciudadanos --}}
            <section aria-labelledby="noshows-heading">
                <h2 class="h6 fw-semibold mb-3" id="noshows-heading">No-shows de ciudadanos hoy</h2>

                @if($this->noshowsCiudadanos->isEmpty())
                    <div class="op-empty op-empty--compact">
                        <x-heroicon-o-check-circle class="op-empty__icon" aria-hidden="true"/>
                        <p class="op-empty__text">Sin no-shows registrados hoy.</p>
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Hora</th>
                                <th>Ciudadano/a</th>
                                <th>Profesional</th>
                                <th>Tipo</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->noshowsCiudadanos as $cita)
                            <tr>
                                <td class="fw-medium">{{ substr($cita->hora_inicio, 0, 5) }}</td>
                                <td>{{ $cita->ciudadano?->nombre_completo ?? '—' }}</td>
                                <td class="text-body-secondary">{{ $cita->profesional?->profesional?->nombre_completo ?? $cita->profesional?->email ?? '—' }}</td>
                                <td class="text-body-secondary">{{ $cita->tipoSlot?->nombre ?? '—' }}</td>
                                <td class="text-end">
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            wire:click="liberarSlot({{ $cita->id }})"
                                            wire:confirm="¿Liberar el slot de esta cita para que vuelva a estar disponible?">
                                        Liberar slot
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

    </div>

    {{-- Panel lateral de reasignación (offcanvas Bootstrap) --}}
    @if($panelAbierto && $citaSeleccionadaId)
    <div class="offcanvas offcanvas-end show"
         tabindex="-1"
         aria-labelledby="reasignacion-label"
         style="width: 400px;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-semibold" id="reasignacion-label">Reasignar cita</h5>
            <button type="button" class="btn-close"
                    wire:click="cerrarPanelReasignacion" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-0">
            <livewire:agenda.supervisor.partials.reasignacion-panel
                :cita-id="$citaSeleccionadaId"
                :key="'panel-' . $citaSeleccionadaId"
                @cita-reasignada="cerrarPanelReasignacion()" />
        </div>
    </div>
    <div class="offcanvas-backdrop fade show" wire:click="cerrarPanelReasignacion"></div>
    @endif

</div>
