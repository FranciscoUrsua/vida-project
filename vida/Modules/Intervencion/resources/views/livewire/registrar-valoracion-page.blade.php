<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">

    {{-- Navegación --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}"
           style="font-size: 0.82rem; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i>
            Volver a la Historia Social
        </a>
    </div>

    <h1 style="font-size: 1.1rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 1.25rem;">Registrar valoración</h1>

    {{-- Selector de ficha --}}
    <div style="margin-bottom: 1.25rem;">
        <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.3rem;">
            Tipo de ficha
        </label>
        <select wire:change="seleccionarFicha($event.target.value)"
                style="width: 100%; max-width: 420px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; background: #fff; color: var(--color-ink-900);">
            <option value="">Selecciona una ficha…</option>
            @foreach($this->fichasDisponibles as $id => $nombre)
                <option value="{{ $id }}" @selected($id == $tipoFichaId)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('tipoFichaId')
            <p style="font-size: 0.75rem; color: var(--color-danger); margin: 0.25rem 0 0;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Formulario de la ficha --}}
    @if($tipoFichaId && $this->tipoFicha)

        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.25rem;">
            {{ $this->tipoFicha->nombre }}
        </h2>

        @if($this->tipoFicha->descripcion)
            <p style="font-size: 0.8rem; color: var(--color-ink-500); margin: 0 0 1.1rem; line-height: 1.5;">
                {{ $this->tipoFicha->descripcion }}
            </p>
        @else
            <div style="margin-bottom: 1rem;"></div>
        @endif

        @foreach($this->tipoFicha->schema['campos'] ?? [] as $campo)
            <div style="margin-bottom: 1rem;">

                <label style="font-size: 0.8rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">
                    {{ $campo['etiqueta'] ?? $campo['id'] }}
                    @if($campo['obligatorio'] ?? false)
                        <span style="color: var(--color-danger);" aria-hidden="true"> *</span>
                    @endif
                </label>

                @if($campo['descripcion'] ?? null)
                    <p style="font-size: 0.73rem; color: var(--color-ink-400); margin: 0 0 0.3rem; line-height: 1.4;">
                        {{ $campo['descripcion'] }}
                    </p>
                @endif

                @php $tipo = $campo['tipo'] ?? 'texto'; @endphp

                @if($tipo === 'texto')
                    <textarea wire:model.live="datos.{{ $campo['id'] }}"
                              rows="2"
                              style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; resize: vertical; font-family: inherit; color: var(--color-ink-900);"></textarea>

                @elseif($tipo === 'numero')
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <input type="number"
                               wire:model.live="datos.{{ $campo['id'] }}"
                               style="width: 140px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; color: var(--color-ink-900);">
                        @if($campo['unidad'] ?? null)
                            <span style="font-size: 0.78rem; color: var(--color-ink-500);">{{ $campo['unidad'] }}</span>
                        @endif
                    </div>

                @elseif($tipo === 'select')
                    <select wire:model.live="datos.{{ $campo['id'] }}"
                            style="width: 100%; max-width: 320px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; background: #fff; color: var(--color-ink-900);">
                        <option value="">Selecciona…</option>
                        @foreach($campo['opciones'] ?? [] as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>

                @elseif($tipo === 'booleano')
                    <div style="display: flex; gap: 1rem; margin-top: 0.15rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; cursor: pointer; color: var(--color-ink-800);">
                            <input type="radio"
                                   wire:model.live="datos.{{ $campo['id'] }}"
                                   value="1">
                            Sí
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.82rem; cursor: pointer; color: var(--color-ink-800);">
                            <input type="radio"
                                   wire:model.live="datos.{{ $campo['id'] }}"
                                   value="0">
                            No
                        </label>
                    </div>

                @elseif($tipo === 'fecha')
                    <input type="date"
                           wire:model.live="datos.{{ $campo['id'] }}"
                           style="padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; color: var(--color-ink-900);">

                @elseif($tipo === 'escala')
                    {{-- Solo puntuación total; el pase completo se hace en módulo Escalas --}}
                    <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <input type="number"
                               wire:model.live="datos.{{ $campo['id'] }}"
                               min="0"
                               style="width: 100px; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; color: var(--color-ink-900);">
                        <span style="font-size: 0.73rem; color: var(--color-ink-400);">puntuación total</span>
                    </div>
                @endif

                @error("datos.{$campo['id']}")
                    <p style="font-size: 0.75rem; color: var(--color-danger); margin: 0.2rem 0 0;">{{ $message }}</p>
                @enderror

            </div>
        @endforeach

        {{-- Notas --}}
        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--color-ink-100);">
            <label style="font-size: 0.78rem; font-weight: 600; color: var(--color-ink-700); display: block; margin-bottom: 0.25rem;">
                Notas libres
            </label>
            <p style="font-size: 0.73rem; color: var(--color-ink-400); margin: 0 0 0.35rem;">
                Observaciones no estructuradas de la entrevista.
            </p>
            <textarea wire:model.live="notas"
                      rows="3"
                      style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.82rem; border: 1px solid var(--color-ink-300); border-radius: 6px; resize: vertical; font-family: inherit; color: var(--color-ink-900);"></textarea>
        </div>

        {{-- Feedback de guardado --}}
        @if($estadoGuardado === 'borrador')
            <div style="margin-top: 1rem; padding: 0.6rem 0.9rem; background: var(--color-ink-50, #f8fafc); border-radius: 6px; border: 1px solid var(--color-ink-200, #e2e8f0); font-size: 0.82rem; color: var(--color-ink-600); display: flex; align-items: center; gap: 0.4rem;">
                <i data-lucide="save" style="width:15px;height:15px; flex-shrink:0;" aria-hidden="true"></i>
                Borrador guardado.
            </div>
        @endif

        {{-- Acciones --}}
        <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1.25rem;">
            <button wire:click="guardarDefinitivo"
                    style="padding: 0.45rem 1.1rem; font-size: 0.85rem; font-weight: 600; background: var(--color-primary); color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                Guardar
            </button>
            <button wire:click="guardar"
                    style="padding: 0.45rem 1.1rem; font-size: 0.85rem; font-weight: 500; background: transparent; color: var(--color-primary); border: 1.5px solid var(--color-primary); border-radius: 6px; cursor: pointer;">
                Guardar borrador
            </button>
            <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}"
               style="font-size: 0.82rem; color: var(--color-ink-500); text-decoration: none;">
                Cancelar
            </a>
        </div>

    @elseif(! $tipoFichaId)
        <p style="font-size: 0.85rem; color: var(--color-ink-500); margin-top: 0.5rem;">
            Selecciona un tipo de ficha para comenzar.
        </p>
    @endif

</div>
