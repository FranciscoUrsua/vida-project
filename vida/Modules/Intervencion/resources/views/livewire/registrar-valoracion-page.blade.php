{{-- Valoración desde la Historia Social (RegistrarValoracionPage) o desde un encargo (ValoracionEncargoPage) --}}
<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    {{-- Navegación --}}
    <a href="{{ $this->volverUrl() }}"
       class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1 mb-3">
        <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/>
        {{ $this->volverTexto() }}
    </a>

    <h1 class="h5 fw-bold mb-3">Registrar valoración</h1>

    {{-- Selector de ficha --}}
    <div class="mb-4">
        <label for="valoracion-tipo-ficha" class="form-label small fw-semibold">Tipo de ficha</label>
        <select id="valoracion-tipo-ficha" wire:change="seleccionarFicha($event.target.value)"
                class="form-select form-select-sm w-auto mw-100 @error('tipoFichaId') is-invalid @enderror">
            <option value="">Selecciona una ficha…</option>
            @foreach($this->fichasDisponibles as $id => $nombre)
                <option value="{{ $id }}" @selected($id == $tipoFichaId)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('tipoFichaId')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Formulario de la ficha --}}
    @if($tipoFichaId && $this->tipoFicha)

        <div class="card card-body">
            <h2 class="h6 fw-bold text-primary mb-1">
                {{ $this->tipoFicha->nombre }}
            </h2>

            @if($this->tipoFicha->descripcion)
                <p class="small text-body-secondary mb-3">
                    {{ $this->tipoFicha->descripcion }}
                </p>
            @endif

            <x-ficha.campos :campos="$this->tipoFicha->schema['campos'] ?? []" prefijo="datos" />

            {{-- Notas --}}
            <div class="border-top pt-3 mt-2">
                <label for="valoracion-notas" class="form-label small fw-semibold mb-1">Notas libres</label>
                <p class="form-text mt-0 mb-1">Observaciones no estructuradas de la entrevista.</p>
                <textarea id="valoracion-notas" wire:model.live="notas" rows="3" class="form-control form-control-sm"></textarea>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="d-flex gap-2 mt-4">
            <button type="button" wire:click="guardarDefinitivo" class="btn btn-primary">
                Guardar
            </button>
            <a href="{{ $this->volverUrl() }}" class="btn btn-outline-secondary">
                Cancelar
            </a>
        </div>

    @elseif(! $tipoFichaId)
        <p class="text-body-secondary">
            Selecciona un tipo de ficha para comenzar.
        </p>
    @endif

</div>
</div>
</div>
