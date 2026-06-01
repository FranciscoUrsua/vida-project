<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">

    <div style="margin-bottom: 1rem;">
        <a href="{{ route('intervencion.ciudadano.show', $historia->id) }}" style="font-size: 0.82rem; color: #534AB7; text-decoration: none;">
            <i class="bi bi-arrow-left"></i> Volver a la Historia Social
        </a>
    </div>

    <h1 style="font-size: 1.1rem; font-weight: 700; color: #1D160E; margin: 0 0 1.25rem;">Aplicar escala</h1>

    @if($this->tipoEscala)
        <h2 style="font-size: 0.95rem; font-weight: 700; color: #534AB7; margin: 0 0 0.5rem;">{{ $this->tipoEscala->nombre }}</h2>
        @if($this->tipoEscala->instrucciones_aplicacion)
            <p style="font-size: 0.8rem; color: #6B7280; margin: 0 0 1.25rem; line-height: 1.5;">
                {{ $this->tipoEscala->instrucciones_aplicacion }}
            </p>
        @endif

        @foreach($this->tipoEscala->schema['secciones'] ?? [] as $seccion)
            <div style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 0.85rem; font-weight: 700; color: #374151; margin: 0 0 0.75rem; padding-bottom: 0.3rem; border-bottom: 1px solid #E5E3F5;">
                    {{ $seccion['titulo'] }}
                </h3>
                @foreach($seccion['items'] ?? [] as $item)
                    <div style="margin-bottom: 0.75rem; padding: 0.6rem 0.75rem; background: #fff; border: 1px solid #F3F4F6; border-radius: 6px;">
                        <div style="font-size: 0.82rem; font-weight: 600; color: #1D160E; margin-bottom: 0.35rem;">{{ $item['texto'] }}</div>
                        @if($item['instrucciones'] ?? null)
                            <div style="font-size: 0.72rem; color: #9CA3AF; margin-bottom: 0.35rem;">{{ $item['instrucciones'] }}</div>
                        @endif
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            @foreach($item['opciones'] ?? [] as $opcion)
                                <label style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.78rem; cursor: pointer; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid #E5E3F5; background: #FAFAFA;">
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
        <p style="color: #6B7280; font-size: 0.85rem;">No se encontró el instrumento seleccionado.</p>
    @endif

</div>
