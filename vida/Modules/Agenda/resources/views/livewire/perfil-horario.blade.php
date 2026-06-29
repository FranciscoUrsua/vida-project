<div>
    {{-- Jornada y vigencia --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <label class="form-label" for="ph-jornada">Jornada semanal (horas)</label>
            <input id="ph-jornada" type="number" wire:model="jornadaSemanal"
                   class="form-control @error('jornadaSemanal') is-invalid @enderror"
                   step="0.5" min="0" max="40">
            @error('jornadaSemanal') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="ph-vigente">Vigente desde <span class="text-danger">*</span></label>
            <input id="ph-vigente" type="date" wire:model="vigenteDesde"
                   class="form-control @error('vigenteDesde') is-invalid @enderror">
            @error('vigenteDesde') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Días laborables --}}
    <div class="mb-3">
        <label class="form-label d-block">Días laborables</label>
        <div class="d-flex gap-2">
            @foreach ([1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V'] as $num => $letra)
            <button type="button"
                    wire:click="toggleDia({{ $num }})"
                    class="btn btn-sm rounded-circle fw-medium {{ in_array($num, $diasActivos) ? 'btn-primary' : 'btn-outline-secondary' }}"
                    style="width:32px;height:32px;padding:0"
                    title="{{ ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'][$num] }}">
                {{ $letra }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Franjas horarias por día --}}
    @if(!empty($diasActivos))
    <div class="mb-3">
        <label class="form-label">Franjas horarias</label>
        @foreach ($diasActivos as $dia)
        @php $f = $franjasPorDia[$dia] ?? ['mIni' => '09:00', 'mFin' => '14:00', 'tIni' => null, 'tFin' => null]; @endphp
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="text-body-secondary small fw-medium" style="width:1.5rem">
                {{ ['', 'L', 'M', 'X', 'J', 'V'][$dia] }}
            </span>
            <input type="time" wire:model="franjasPorDia.{{ $dia }}.mIni"
                   class="form-control form-control-sm" style="width:7rem">
            <span class="text-body-secondary">–</span>
            <input type="time" wire:model="franjasPorDia.{{ $dia }}.mFin"
                   class="form-control form-control-sm" style="width:7rem">
            @if(empty($f['tIni']))
            <button type="button" wire:click="addTarde({{ $dia }})"
                    class="btn btn-outline-secondary btn-sm">
                + Tarde
            </button>
            @else
            <span class="text-body-secondary">|</span>
            <input type="time" wire:model="franjasPorDia.{{ $dia }}.tIni"
                   class="form-control form-control-sm" style="width:7rem">
            <span class="text-body-secondary">–</span>
            <input type="time" wire:model="franjasPorDia.{{ $dia }}.tFin"
                   class="form-control form-control-sm" style="width:7rem">
            <button type="button" wire:click="removeTarde({{ $dia }})"
                    class="btn btn-outline-danger btn-sm px-2">
                <x-heroicon-o-x-mark class="icon-14" aria-hidden="true"/>
            </button>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    {{-- Notas --}}
    <div class="mb-3">
        <label class="form-label" for="ph-notas">Notas</label>
        <textarea id="ph-notas" wire:model="notas"
                  class="form-control" rows="2"
                  placeholder="Observaciones sobre el perfil horario…"></textarea>
    </div>

    {{-- Acción --}}
    <div class="d-flex gap-2">
        <button type="button"
                wire:click="guardar"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-sm">
            <span wire:loading wire:target="guardar"
                  class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
            Guardar perfil horario
        </button>
    </div>
</div>
