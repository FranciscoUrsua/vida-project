<div class="op-page">

    {{-- Filtros --}}
    <section class="p-3 border-bottom">
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label small" for="fechaDesde">Desde</label>
                <input id="fechaDesde" type="date" class="form-control form-control-sm" wire:model.live="fechaDesde">
            </div>
            <div>
                <label class="form-label small" for="fechaHasta">Hasta</label>
                <input id="fechaHasta" type="date" class="form-control form-control-sm" wire:model.live="fechaHasta">
            </div>
            <div>
                <label class="form-label small" for="profesionalId">Profesional</label>
                <select id="profesionalId" class="form-select form-select-sm" wire:model.live="profesionalId">
                    <option value="">Todos</option>
                    @foreach($this->profesionales as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small" for="ciudadano">Ciudadano</label>
                <input id="ciudadano" type="search" class="form-control form-control-sm"
                       placeholder="Nombre o apellidos" wire:model.live.debounce.400ms="ciudadano">
            </div>
            @if($this->tieneColectivosProtegidos)
            <div class="form-check align-self-end pb-1">
                <input class="form-check-input" type="checkbox" id="soloProtegidos" wire:model.live="soloProtegidos">
                <label class="form-check-label" for="soloProtegidos">Solo colectivos protegidos</label>
            </div>
            <div class="form-check align-self-end pb-1">
                <input class="form-check-input" type="checkbox" id="soloSinAutorizacion" wire:model.live="soloSinAutorizacion">
                <label class="form-check-label" for="soloSinAutorizacion">Sin autorización</label>
            </div>
            @endif
        </div>
    </section>

    {{-- Tabla de accesos --}}
    <section class="p-3">
        @if($this->accesos->isEmpty())
            <x-op.empty icono="shield-check">No hay accesos que coincidan con los filtros seleccionados.</x-op.empty>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <x-op.th-orden columna="profesional" :actual="$ordenarPor" :direccion="$direccion">Profesional</x-op.th-orden>
                        <x-op.th-orden columna="ciudadano" :actual="$ordenarPor" :direccion="$direccion">Ciudadano</x-op.th-orden>
                        @if($this->tieneColectivosProtegidos)
                        <th></th>
                        @endif
                        <x-op.th-orden columna="fecha" :actual="$ordenarPor" :direccion="$direccion">Fecha y hora</x-op.th-orden>
                        <x-op.th-orden columna="accion" :actual="$ordenarPor" :direccion="$direccion">Acción</x-op.th-orden>
                        <x-op.th-orden columna="motivo" :actual="$ordenarPor" :direccion="$direccion">Motivo declarado</x-op.th-orden>
                        @if($this->tieneColectivosProtegidos)
                        <th>Estado</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->accesos as $acceso)
                    <tr>
                        <td>
                            {{ $acceso->user?->profesional?->nombre_completo ?? $acceso->user?->email ?? '—' }}
                        </td>
                        <td>
                            {{ $acceso->ciudadano?->nombre_completo ?? '—' }}
                        </td>
                        @if($this->tieneColectivosProtegidos)
                        <td class="text-center">
                            @if($this->protegidosPorCiudadano[$acceso->ciudadano_id] ?? false)
                                <x-heroicon-s-shield-exclamation class="icon-16 text-warning"
                                    aria-label="Colectivo protegido"/>
                            @endif
                        </td>
                        @endif
                        <td class="text-nowrap text-body-secondary">
                            {{ $acceso->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            {{ $acceso->accion?->etiqueta() ?? '—' }}
                        </td>
                        <td class="text-body-secondary">
                            {{ $acceso->contexto['motivo'] ?? '—' }}
                        </td>
                        @if($this->tieneColectivosProtegidos)
                        <td>
                            @if($acceso->accion === \App\Enums\AccionAuditEnum::AccesoRestringido)
                                <span class="badge bg-danger-subtle text-danger-emphasis">Sin autorización</span>
                            @else
                                <span class="badge bg-success-subtle text-success-emphasis">Normal</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->accesos->hasPages())
        <div class="mt-3">
            {{ $this->accesos->links() }}
        </div>
        @endif
        @endif
    </section>

</div>
