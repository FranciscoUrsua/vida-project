<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">

    <div style="margin-bottom: 1rem;">
        <a href="{{ route('intervencion.ciudadano.show', $historia->id) }}" style="font-size: 0.82rem; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i> Volver a la Historia Social
        </a>
    </div>

    <h1 style="font-size: 1.1rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 1.25rem;">Registrar valoración</h1>

    @if(! $tipoFichaId)
        <div style="background: var(--color-danger-soft); padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; color: var(--color-danger-ink); margin-bottom: 1rem;">
            @error('tipoFichaId') {{ $message }} @enderror
            Selecciona un tipo de ficha para continuar.
        </div>
    @endif

    @if($this->tipoFicha)
        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 1rem;">{{ $this->tipoFicha->nombre }}</h2>

        @foreach($this->tipoFicha->schema['campos'] ?? [] as $campo)
            <div style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">
                    {{ $campo['etiqueta'] ?? $campo['id'] }}
                    @if($campo['obligatorio'] ?? false)
                        <span style="color: var(--color-danger);">*</span>
                    @endif
                </label>
                @if(($campo['tipo'] ?? 'texto') === 'texto')
                    <textarea wire:model="respuestas.{{ $campo['id'] }}" rows="2" class="form-control form-control-sm"></textarea>
                @elseif($campo['tipo'] === 'numero')
                    <input type="number" wire:model="respuestas.{{ $campo['id'] }}" class="form-control form-control-sm">
                @elseif($campo['tipo'] === 'select')
                    <select wire:model="respuestas.{{ $campo['id'] }}" class="form-select form-select-sm">
                        <option value="">Selecciona...</option>
                        @foreach($campo['opciones'] ?? [] as $opcion)
                            <option value="{{ $opcion['valor'] }}">{{ $opcion['etiqueta'] }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" wire:model="respuestas.{{ $campo['id'] }}" class="form-control form-control-sm">
                @endif
            </div>
        @endforeach

        <button wire:click="guardar" class="btn btn-primary" style="font-size: 0.85rem; margin-top: 0.5rem;">
            Guardar valoración
        </button>
    @endif

</div>
