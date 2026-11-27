{{-- Selector con búsqueda de un campo de ficha de tipo catálogo (docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque B) --}}
<div>
    @if($this->etiquetaElegida !== null)
        <div class="d-flex align-items-center gap-2 small">
            <span class="fw-semibold" id="{{ $idCampo }}">{{ $this->etiquetaElegida }}</span>
            @unless($deshabilitado)
                <button type="button" wire:click="quitar" class="btn btn-link btn-sm p-0">Cambiar</button>
            @endunless
        </div>
    @elseif(! $deshabilitado)
        <input id="{{ $idCampo }}" type="search" wire:model.live.debounce.300ms="busqueda" class="form-control form-control-sm"
               placeholder="{{ $this->hayQueEscribir() ? 'Escribe para buscar' : 'Escribe para filtrar' }}" autocomplete="off" />
        @if(count($this->resultados) > 0)
            <div class="list-group op-lista-scroll mt-1" role="listbox" aria-label="Valores del catálogo">
                @foreach($this->resultados as $opcion)
                    <button type="button" class="list-group-item list-group-item-action py-1 small" role="option"
                            wire:key="opcion-{{ $opcion['clave'] }}" wire:click="elegir(@js($opcion['clave']))">
                        {{ $opcion['etiqueta'] }}
                    </button>
                @endforeach
            </div>
        @elseif(trim($busqueda) !== '')
            <div class="form-text">Ningún valor contiene «{{ $busqueda }}».</div>
        @endif
    @else
        <p class="small text-body-tertiary fst-italic mb-0">Sin respuesta</p>
    @endif
</div>
