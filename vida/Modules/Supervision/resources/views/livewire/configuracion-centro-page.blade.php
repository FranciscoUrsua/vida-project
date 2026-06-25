<div class="op-page">

    <div class="op-toolbar">
        <h2 class="h5 mb-0 fw-semibold">Configuración del centro</h2>
    </div>

    <form wire:submit="guardar">

        {{-- Identificación del centro --}}
        <section class="op-section" aria-labelledby="identidad-heading">
            <h3 class="h6 fw-semibold mb-3" id="identidad-heading">Identificación del centro</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombreCorto">Nombre corto</label>
                    <input id="nombreCorto" type="text" class="form-control" wire:model="nombreCorto" maxlength="50">
                    @error('nombreCorto') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </section>

        {{-- Horario y agenda --}}
        <section class="op-section" aria-labelledby="agenda-heading">
            <h3 class="h6 fw-semibold mb-3" id="agenda-heading">Horario y agenda</h3>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="modoAgenda">Modo de agenda</label>
                    <select id="modoAgenda" class="form-select" wire:model.live="modoAgenda">
                        <option value="basico">Básico</option>
                        <option value="estandar">Estándar</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                    @if($mostrarAdvertenciaModoAgenda)
                    <div class="alert alert-warning d-flex align-items-start gap-2 mt-2 mb-0 py-2">
                        <x-heroicon-o-exclamation-triangle class="icon-16 flex-shrink-0 mt-1" aria-hidden="true"/>
                        <small>Este cambio afectará a la interfaz de todos los profesionales del centro.</small>
                    </div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="umbralRatio">Umbral ratio personas/profesional</label>
                    <input id="umbralRatio" type="number" step="0.1" min="1" max="100" class="form-control" wire:model="umbralRatio">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="umbralEsperaDias">Umbral espera media (días)</label>
                    <input id="umbralEsperaDias" type="number" min="1" max="365" class="form-control" wire:model="umbralEsperaDias">
                </div>
            </div>
        </section>

        @if($this->tienePlazas)
        {{-- Plazas --}}
        <section class="op-section" aria-labelledby="plazas-heading">
            <h3 class="h6 fw-semibold mb-3" id="plazas-heading">Plazas</h3>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="capacidadMaxima">Capacidad máxima declarada</label>
                    <input id="capacidadMaxima" type="number" min="0" class="form-control">
                    <div class="form-text">Informativo. No impide crear más plazas.</div>
                </div>
            </div>
        </section>
        @endif

        <div class="d-flex gap-2 px-3 pb-3">
            <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
        </div>

    </form>

</div>
