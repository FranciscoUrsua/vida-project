<div class="op-page d-flex flex-column gap-3 p-3">

    <header class="d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <p class="small text-uppercase fw-semibold text-body-secondary mb-0">Citación</p>
            <h1 class="h4 fw-bold mb-0">Nueva cita</h1>
        </div>
        <a href="{{ route('agenda.citas.bandeja') }}" wire:navigate class="btn btn-outline-secondary btn-sm">Volver a la bandeja</a>
    </header>

    <section class="card card-body" aria-labelledby="titulo-persona">
        <h2 id="titulo-persona" class="h6 fw-bold mb-3">Persona</h2>
        @if($this->persona)
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="fw-semibold">{{ $this->persona->nombre_completo }}</span>
                @if($this->persona->documentoVigente)
                    <span class="small font-monospace text-body-secondary">{{ $this->persona->documentoVigente->valor }}</span>
                @endif
                @if($this->persona->telefono)
                    <span class="small text-body-secondary">{{ $this->persona->telefono }}</span>
                @endif
                <button type="button" wire:click="cambiarPersona" class="btn btn-sm btn-outline-secondary ms-auto">Cambiar</button>
            </div>
        @else
            @include('agenda::livewire.citas.partials.buscar-persona', ['accion' => 'elegirPersona', 'etiqueta' => 'Elegir'])
        @endif
    </section>

    @if($this->persona)
        <section class="card card-body" aria-labelledby="titulo-cita">
            <h2 id="titulo-cita" class="h6 fw-bold mb-3">Cita</h2>
            <div class="mb-2">
                <span class="small fw-semibold me-2">Canal</span>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="canal-presencial" value="presencial" wire:model="canal">
                    <label class="form-check-label small" for="canal-presencial">Presencial</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="canal-telefonico" value="telefonico" wire:model="canal">
                    <label class="form-check-label small" for="canal-telefonico">Teléfono</label>
                </div>
            </div>

            @include('agenda::livewire.citas.partials.formulario-solicitud', ['prefijo' => 'directa', 'conMotivo' => false])

            <div class="d-flex gap-2 mt-3">
                <button type="button" wire:click="buscarHuecos" class="btn btn-primary btn-sm">Buscar huecos</button>
            </div>
        </section>

        @error('citar') <div class="alert alert-danger py-2 mb-0" role="alert">{{ $message }}</div> @enderror

        @if($propuestas !== null)
            <section class="card card-body" aria-labelledby="titulo-huecos">
                <h2 id="titulo-huecos" class="h6 fw-bold mb-3">Huecos propuestos</h2>
                @if($propuestas === [])
                    <x-op.empty icono="calendar">No hay huecos compatibles. Prueba con otro destino o amplía la fecha límite.</x-op.empty>
                @else
                    <div class="list-group">
                        @foreach($propuestas as $p)
                            <div class="list-group-item d-flex flex-wrap align-items-center gap-3 small" wire:key="directa-{{ $p['slot_id'] }}">
                                <span class="fw-semibold">{{ $p['fecha'] }} · {{ $p['hora'] }}</span>
                                <span>{{ $p['profesional'] }}</span>
                                <span class="text-body-secondary">{{ $p['modo'] }}</span>
                                <button type="button" wire:click="citar({{ $p['slot_id'] }})" class="btn btn-sm btn-primary ms-auto">Citar</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    @endif
</div>
