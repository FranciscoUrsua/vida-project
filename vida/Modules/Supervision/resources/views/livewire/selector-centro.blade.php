{{-- Centro supervisado: solo si se puede supervisar más de uno (D1.8) --}}
<div>
    @if($this->centros->count() > 1)
        <div class="px-3 pt-3 pb-2">
            <label for="selector-centro-supervisado" class="form-label small text-body-secondary mb-1">Centro supervisado</label>
            <select id="selector-centro-supervisado"
                    class="form-select form-select-sm"
                    wire:change="elegir($event.target.value)">
                @foreach($this->centros as $centro)
                    <option value="{{ $centro->id }}" @selected($this->centroSupervisado?->id === $centro->id)>{{ $centro->nombre_corto ?: $centro->nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
</div>
