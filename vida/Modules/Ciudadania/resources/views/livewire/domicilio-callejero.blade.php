{{-- Domicilio con autocompletado del callejero oficial (docs/instrucciones-cli/2026-11-callejero-madrid.md, paso 3) --}}
<div>
    @if($domicilio['manual'] ?? false)
        <input id="{{ $idCampo }}" type="text" wire:model="domicilio.texto" class="form-control form-control-sm"
               placeholder="Dirección escrita a mano" autocomplete="off" />
        <div class="form-text">
            Para personas sin domicilio fijo, domicilios fuera de Madrid o direcciones que no están en el callejero.
            @if($hayCallejero)
                <button type="button" wire:click="buscarEnCallejero" class="btn btn-link btn-sm p-0 align-baseline">Buscar en el callejero</button>
            @endif
        </div>
    @else
        @if($codVia === null)
            {{-- 1. Vía --}}
            <input id="{{ $idCampo }}" type="search" wire:model.live.debounce.300ms="busquedaVia" class="form-control form-control-sm"
                   placeholder="Escribe la calle (al menos 3 letras)" autocomplete="off" aria-describedby="{{ $idCampo }}-ayuda" />
            @if(count($this->vias) > 0)
                <div class="list-group op-lista-scroll mt-1" role="listbox" aria-label="Vías del callejero">
                    @foreach($this->vias as $via)
                        <button type="button" class="list-group-item list-group-item-action py-1 small" role="option"
                                wire:key="via-{{ $via['cod_via'] }}" wire:click="elegirVia('{{ $via['cod_via'] }}')">
                            {{ $via['etiqueta'] }}
                            <span class="text-body-secondary">· {{ $via['distritos'] }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif(mb_strlen(trim($busquedaVia)) >= 3)
                <div class="form-text">Ninguna vía del callejero de Madrid contiene «{{ $busquedaVia }}».</div>
            @endif
        @elseif($this->elegido === null)
            {{-- 2. Número --}}
            <div class="d-flex align-items-center gap-2 mb-1 small">
                <span class="fw-semibold">{{ $etiquetaVia }}</span>
                <button type="button" wire:click="cambiarVia" class="btn btn-link btn-sm p-0">Cambiar la vía</button>
            </div>
            <input id="{{ $idCampo }}" type="search" inputmode="numeric" wire:model.live.debounce.300ms="busquedaNumero"
                   class="form-control form-control-sm" placeholder="Número" autocomplete="off" aria-describedby="{{ $idCampo }}-ayuda" />
            @if(count($this->numeros) > 0)
                <div class="list-group op-lista-scroll mt-1" role="listbox" aria-label="Números de la vía">
                    @foreach($this->numeros as $numero)
                        <button type="button" class="list-group-item list-group-item-action py-1 small" role="option"
                                wire:key="numero-{{ $numero['id'] }}" wire:click="elegirNumero({{ $numero['id'] }})">
                            {{ $numero['etiqueta'] }}
                            @if($numero['tipo'])
                                <span class="text-body-secondary">· {{ $numero['tipo'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @else
                <div class="form-text">Este número no está en el callejero para esta vía.</div>
            @endif
        @else
            {{-- 3. Elegido: el resto del domicilio --}}
            <div class="d-flex align-items-center gap-2 mb-2 small">
                <x-heroicon-o-map-pin class="icon-14 text-primary" aria-hidden="true" />
                <span class="fw-semibold">{{ $this->elegido }}</span>
                <button type="button" wire:click="cambiarNumero" class="btn btn-link btn-sm p-0">Cambiar</button>
            </div>
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <label for="{{ $idCampo }}-portal" class="form-label small mb-0">Portal</label>
                    <input id="{{ $idCampo }}-portal" type="text" wire:model="domicilio.portal" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label for="{{ $idCampo }}-escalera" class="form-label small mb-0">Escalera</label>
                    <input id="{{ $idCampo }}-escalera" type="text" wire:model="domicilio.escalera" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label for="{{ $idCampo }}-piso" class="form-label small mb-0">Piso</label>
                    <input id="{{ $idCampo }}-piso" type="text" wire:model="domicilio.piso" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label for="{{ $idCampo }}-puerta" class="form-label small mb-0">Puerta</label>
                    <input id="{{ $idCampo }}-puerta" type="text" wire:model="domicilio.puerta" class="form-control form-control-sm" />
                </div>
            </div>
        @endif
        <div id="{{ $idCampo }}-ayuda" class="form-text">
            Callejero oficial de Madrid.
            <button type="button" wire:click="escribirAMano" class="btn btn-link btn-sm p-0 align-baseline">Escribir la dirección a mano</button>
        </div>
    @endif
</div>
