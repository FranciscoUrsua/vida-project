<div class="op-page">

    {{-- Filtros --}}
    <section class="p-3 border-bottom">
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label form-label-sm" for="fechaDesde">Desde</label>
                <input id="fechaDesde" type="date" class="form-control form-control-sm" wire:model.live="fechaDesde">
            </div>
            <div>
                <label class="form-label form-label-sm" for="fechaHasta">Hasta</label>
                <input id="fechaHasta" type="date" class="form-control form-control-sm" wire:model.live="fechaHasta">
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
            <div class="op-empty">
                <x-heroicon-o-shield-check class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay accesos registrados en el periodo seleccionado.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Profesional</th>
                        <th>Ciudadano</th>
                        @if($this->tieneColectivosProtegidos)
                        <th></th>
                        @endif
                        <th>Fecha y hora</th>
                        <th>Acción</th>
                        <th>Motivo declarado</th>
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
