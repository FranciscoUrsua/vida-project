{{-- Escala desde la Historia Social (RegistrarEscalaPage) o desde un encargo (EscalaEncargoPage) --}}
<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    <a href="{{ $this->volverUrl() }}"
       class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1 mb-3">
        <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/> {{ $this->volverTexto() }}
    </a>

    <h1 class="h5 fw-bold mb-3">Aplicar escala</h1>

    {{-- Selector: solo desde un encargo, con las escalas de su área (EA-2) --}}
    @if(($escalas = $this->escalasDisponibles()) !== null)
        <div class="mb-4">
            <label for="escala-tipo" class="form-label small fw-semibold">Escala</label>
            <select id="escala-tipo" wire:model.live="tipoEscalaId"
                    class="form-select form-select-sm w-auto mw-100 @error('tipoEscalaId') is-invalid @enderror">
                <option value="">Selecciona una escala…</option>
                @foreach($escalas as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
            @error('tipoEscalaId') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endif

    @if($this->tipoEscala)
        <h2 class="h6 fw-bold text-primary mb-2">{{ $this->tipoEscala->nombre }}</h2>
        @if($this->tipoEscala->instrucciones_aplicacion)
            <p class="small text-body-secondary mb-4">
                {{ $this->tipoEscala->instrucciones_aplicacion }}
            </p>
        @endif

        @foreach($this->tipoEscala->schema['secciones'] ?? [] as $seccion)
            <section class="mb-4">
                <h3 class="small fw-bold border-bottom pb-1 mb-3">
                    {{ $seccion['titulo'] }}
                </h3>
                @foreach($seccion['items'] ?? [] as $item)
                    <fieldset class="card card-body py-2 mb-2">
                        <legend class="fs-6 small fw-semibold mb-1">{{ $item['texto'] }}</legend>
                        @if($item['instrucciones'] ?? null)
                            <p class="form-text mt-0 mb-2">{{ $item['instrucciones'] }}</p>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($item['opciones'] ?? [] as $opcion)
                                @php $idOpcion = 'escala-'.$item['id'].'-'.$loop->index; @endphp
                                <input type="radio" class="btn-check" id="{{ $idOpcion }}" autocomplete="off"
                                       wire:model="respuestas.{{ $item['id'] }}"
                                       value="{{ $opcion['valor'] }}">
                                <label class="btn btn-outline-secondary btn-sm" for="{{ $idOpcion }}">
                                    {{ $opcion['etiqueta'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </section>
        @endforeach

        <button type="button" wire:click="guardar" class="btn btn-primary btn-sm">
            Guardar escala
        </button>
    @elseif($escalas !== null)
        <p class="text-body-secondary">Selecciona una escala para comenzar.</p>
    @else
        <p class="text-body-secondary">No se encontró el instrumento seleccionado.</p>
    @endif

</div>
</div>
</div>
