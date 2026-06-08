<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">

    <div style="margin-bottom: 1rem;">
        <a href="{{ route('intervencion.ciudadano.show', $historia->id) }}" style="font-size: 0.82rem; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i> Volver a la Historia Social
        </a>
    </div>

    <h1 style="font-size: 1.1rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 1.25rem;">Aplicar escala</h1>

    @if($this->tipoEscala)
        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--color-primary); margin: 0 0 0.5rem;">{{ $this->tipoEscala->nombre }}</h2>
        @if($this->tipoEscala->instrucciones_aplicacion)
            <p style="font-size: 0.8rem; color: var(--color-ink-600); margin: 0 0 1.25rem; line-height: 1.5;">
                {{ $this->tipoEscala->instrucciones_aplicacion }}
            </p>
        @endif

        @foreach($this->tipoEscala->schema['secciones'] ?? [] as $seccion)
            <div style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 0.85rem; font-weight: 700; color: var(--color-ink-700); margin: 0 0 0.75rem; padding-bottom: 0.3rem; border-bottom: 1px solid var(--color-ink-200);">
                    {{ $seccion['titulo'] }}
                </h3>
                @foreach($seccion['items'] ?? [] as $item)
                    <div style="margin-bottom: 0.75rem; padding: 0.6rem 0.75rem; background: #fff; border: 1px solid var(--color-ink-100); border-radius: 6px;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: var(--color-ink-900); margin-bottom: 0.35rem;">{{ $item['texto'] }}</div>
                        @if($item['instrucciones'] ?? null)
                            <div style="font-size: 0.72rem; color: var(--color-ink-400); margin-bottom: 0.35rem;">{{ $item['instrucciones'] }}</div>
                        @endif
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            @foreach($item['opciones'] ?? [] as $opcion)
                                <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.78rem; cursor: pointer; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--color-ink-200); background: var(--color-paper);">
                                    <input type="radio"
                                           wire:model="respuestas.{{ $item['id'] }}"
                                           value="{{ $opcion['valor'] }}">
                                    {{ $opcion['etiqueta'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <button wire:click="guardar" class="btn btn-primary" style="font-size: 0.85rem;">
            Guardar escala
        </button>
    @else
        <p style="color: var(--color-ink-600); font-size: 0.85rem;">No se encontró el instrumento seleccionado.</p>
    @endif

</div>
