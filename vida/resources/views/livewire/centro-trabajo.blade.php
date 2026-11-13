{{-- Centro de trabajo del usuario en la barra superior (docs/modulo-tipo-centro.md, D1.6) --}}
<div class="d-flex align-items-center flex-shrink-0 pe-2">
    @if($this->centros->count() > 1)
        <div class="dropdown">
            <button type="button"
                    class="btn btn-sm btn-light dropdown-toggle text-truncate mw-100 border-0 shadow-none"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Centro de trabajo: {{ $this->centroActivo ? $this->etiqueta($this->centroActivo) : 'sin elegir' }}. Cambiar de centro">
                {{ $this->centroActivo ? $this->etiqueta($this->centroActivo) : 'Elige centro' }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach($this->centros as $centro)
                    <li>
                        <button type="button"
                                wire:click="elegir({{ $centro->id }})"
                                @class(['dropdown-item', 'active' => $this->centroActivo?->id === $centro->id])
                                @if($this->centroActivo?->id === $centro->id) aria-current="true" @endif>
                            {{ $this->etiqueta($centro) }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif($this->centroActivo)
        <span class="small fw-semibold text-body-secondary text-truncate">{{ $this->etiqueta($this->centroActivo) }}</span>
    @elseif($this->uoSinCentro)
        <span class="small fw-semibold text-body-secondary text-truncate">{{ $this->uoSinCentro->nombre }}</span>
    @endif
</div>
