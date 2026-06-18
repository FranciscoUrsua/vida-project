<div style="padding: 1.5rem; max-width: 760px; margin: 0 auto;">

    {{-- Navegación --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}"
           style="font-size: 0.82rem; color: var(--color-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
            <i data-lucide="arrow-left" style="width:14px;height:14px;" aria-hidden="true"></i>
            Volver a la Historia Social
        </a>
    </div>

    {{-- Cabecera --}}
    <div style="margin-bottom: 1.5rem;">
        <p style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-400); margin: 0 0 0.2rem;">Ficha de valoración</p>
        <h1 style="font-size: 1.1rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 0.4rem;">{{ $this->nombreFicha() }}</h1>
        <p style="font-size: 0.78rem; color: var(--color-ink-400); margin: 0;">
            Guardada el {{ $ficha->created_at->translatedFormat('j M Y') }}
            @if($ficha->profesional_id)
                · {{ $ficha->profesional?->name ?? '—' }}
            @endif
        </p>
    </div>

    {{-- Campos --}}
    @php $campos = $this->camposConValor(); @endphp

    @if(empty($campos))
        <p style="font-size: 0.85rem; color: var(--color-ink-400);">Esta ficha no tiene campos registrados.</p>
    @else
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @foreach($campos as $campo)
                <div style="padding: 0.75rem 1rem; background: var(--color-ink-50, #f8fafc); border-radius: 8px; border: 1px solid var(--color-ink-100);">
                    <p style="font-size: 0.73rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-ink-400); margin: 0 0 0.3rem;">
                        {{ $campo['etiqueta'] }}
                    </p>
                    @if($campo['valor'] !== null && $campo['valor'] !== '')
                        <p style="font-size: 0.88rem; color: var(--color-ink-900); margin: 0;">
                            @if($campo['tipo'] === 'booleano')
                                {{ $campo['valor'] ? 'Sí' : 'No' }}
                            @elseif($campo['tipo'] === 'fecha')
                                {{ \Carbon\Carbon::parse($campo['valor'])->translatedFormat('j M Y') }}
                            @else
                                {{ $campo['valor'] }}
                                @if($campo['unidad'])
                                    <span style="font-size: 0.78rem; color: var(--color-ink-400);">{{ $campo['unidad'] }}</span>
                                @endif
                            @endif
                        </p>
                    @else
                        <p style="font-size: 0.82rem; color: var(--color-ink-300); margin: 0; font-style: italic;">Sin respuesta</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Notas --}}
    @if($ficha->notas)
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-ink-100);">
            <p style="font-size: 0.73rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-ink-400); margin: 0 0 0.5rem;">Notas</p>
            <p style="font-size: 0.85rem; color: var(--color-ink-700); margin: 0; line-height: 1.6; white-space: pre-wrap;">{{ $ficha->notas }}</p>
        </div>
    @endif

    {{-- Inmutabilidad --}}
    <p style="margin-top: 1.5rem; font-size: 0.73rem; color: var(--color-ink-300); text-align: center;">
        Solo lectura · El pasado es inmutable
    </p>

</div>
