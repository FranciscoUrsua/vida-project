<div class="op-page">

    <div class="op-toolbar">
        <h2 class="h5 mb-0 fw-semibold">Auditoría de accesos</h2>
    </div>

    {{-- Filtros --}}
    <section class="op-section">
        <div class="op-filter-row">
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
        </div>
    </section>

    {{-- Tabla de accesos --}}
    <section class="op-section">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Profesional</th>
                        <th>Ciudadano</th>
                        @if($this->tieneColectivosProtegidos)
                        <th>Colectivo protegido</th>
                        @endif
                        <th>Fecha y hora</th>
                        <th>Motivo declarado</th>
                        @if($this->tieneColectivosProtegidos)
                        <th>Estado</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="{{ $this->tieneColectivosProtegidos ? 6 : 4 }}" class="text-center text-body-secondary py-4">
                            Pendiente de integración con AccesosExpedienteQuery.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</div>
