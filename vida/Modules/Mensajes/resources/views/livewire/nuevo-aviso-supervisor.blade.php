{{-- Formulario de aviso del supervisor a todo su equipo --}}
<form wire:submit="enviar" class="d-flex flex-column gap-3">
    @if($confirmacion)
        <div class="alert alert-success d-flex align-items-center gap-2 mb-0" role="status">
            <x-heroicon-o-check-circle class="icon-18 flex-shrink-0" aria-hidden="true"/>
            <span>{{ $confirmacion }}</span>
        </div>
    @endif

    <div>
        <label for="aviso-uo" class="form-label fw-semibold">Equipo</label>
        @if($this->uosPropias->count() > 1)
            <select id="aviso-uo" wire:model="uoId" class="form-select @error('uoId') is-invalid @enderror">
                @foreach($this->uosPropias as $uo)
                    <option value="{{ $uo->id }}">{{ $uo->nombre }}</option>
                @endforeach
            </select>
        @else
            <input id="aviso-uo" type="text" class="form-control @error('uoId') is-invalid @enderror"
                   value="{{ $this->uosPropias->first()?->nombre ?? 'Sin unidad asignada' }}" readonly>
        @endif
        <div class="form-text">Lo recibirán todas las personas adscritas a la unidad. No podrán responderlo.</div>
        @error('uoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div>
        <label for="aviso-titulo" class="form-label fw-semibold">Título</label>
        <input id="aviso-titulo" type="text" wire:model="titulo" maxlength="255"
               class="form-control @error('titulo') is-invalid @enderror">
        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div>
        <label for="aviso-cuerpo" class="form-label fw-semibold">Aviso</label>
        <textarea id="aviso-cuerpo" wire:model="cuerpo" rows="4" maxlength="5000"
                  class="form-control @error('cuerpo') is-invalid @enderror"></textarea>
        @error('cuerpo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
            <x-heroicon-o-megaphone class="icon-16" aria-hidden="true"/> Enviar aviso
        </button>
    </div>
</form>
