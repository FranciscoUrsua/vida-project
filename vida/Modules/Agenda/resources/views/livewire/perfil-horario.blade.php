<div>
    <div class="op-page">
        <h2>Perfil horario — {{ $profesional->name }}</h2>

        <div class="mb-3">
            <label>Jornada semanal (horas)</label>
            <input type="number" wire:model="jornadaSemanal" class="form-control" step="0.5">
        </div>

        <div class="mb-3">
            <label>Vigente desde</label>
            <input type="date" wire:model="vigenteDesde" class="form-control">
        </div>

        <div class="mb-3">
            @foreach ([1=>'L',2=>'M',3=>'X',4=>'J',5=>'V'] as $num => $letra)
                <button wire:click="toggleDia({{ $num }})"
                    class="btn btn-circle {{ in_array($num, $diasActivos) ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $letra }}
                </button>
            @endforeach
        </div>

        @foreach ($diasActivos as $dia)
            <div class="mb-2">
                <strong>Día {{ $dia }}</strong>
                @if (empty($franjasPorDia[$dia]['tIni']))
                    <button wire:click="addTarde({{ $dia }})" class="btn btn-sm btn-outline-primary">+ Añadir tarde</button>
                @else
                    <span>{{ $franjasPorDia[$dia]['tIni'] }}–{{ $franjasPorDia[$dia]['tFin'] }}</span>
                    <button wire:click="removeTarde({{ $dia }})" class="btn btn-sm btn-outline-danger">Quitar tarde</button>
                @endif
            </div>
        @endforeach

        <button wire:click="guardar" class="btn btn-primary mt-3">Guardar perfil horario</button>
    </div>
</div>
