<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    {{-- Navegación --}}
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <a href="{{ route('intervencion.ciudadano.show', $historiaId) }}"
           class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1">
            <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/>
            Volver a la Historia Social
        </a>
        @include('mensajes::partials.boton-escribir-mensaje', ['tipo' => 'ficha', 'id' => $ficha->id])
    </div>

    {{-- Cabecera --}}
    <header class="mb-4">
        <p class="small text-uppercase fw-semibold text-body-secondary mb-1">Ficha de valoración</p>
        <h1 class="h5 fw-bold mb-1">{{ $this->nombreFicha() }}</h1>
        <p class="small text-body-secondary mb-0">
            Guardada el {{ $ficha->created_at->translatedFormat('j M Y') }}
            @if($ficha->profesional_id)
                · {{ $ficha->profesional?->name ?? '—' }}
            @endif
        </p>
    </header>

    {{-- Campos --}}
    @php $campos = $this->camposConValor(); @endphp

    @if(empty($campos))
        <p class="text-body-secondary">Esta ficha no tiene campos registrados.</p>
    @else
        <div class="d-flex flex-column gap-2">
            @foreach($campos as $campo)
                <div class="bg-body-tertiary border rounded p-3">
                    <p class="small text-uppercase fw-semibold text-body-secondary mb-1">
                        {{ $campo['etiqueta'] }}
                    </p>
                    @if($campo['valor'] !== null && $campo['valor'] !== '')
                        <p class="mb-0">
                            @if($campo['tipo'] === 'booleano')
                                {{ $campo['valor'] ? 'Sí' : 'No' }}
                            @elseif($campo['tipo'] === 'fecha')
                                {{ \Carbon\Carbon::parse($campo['valor'])->translatedFormat('j M Y') }}
                            @else
                                {{ $campo['valor'] }}
                                @if($campo['unidad'])
                                    <span class="small text-body-secondary">{{ $campo['unidad'] }}</span>
                                @endif
                            @endif
                        </p>
                    @else
                        <p class="small text-body-tertiary fst-italic mb-0">Sin respuesta</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Notas --}}
    @if($ficha->notas)
        <div class="border-top pt-3 mt-4">
            <p class="small text-uppercase fw-semibold text-body-secondary mb-2">Notas</p>
            <p class="mb-0 text-break">{!! nl2br(e($ficha->notas)) !!}</p>
        </div>
    @endif

    {{-- Inmutabilidad --}}
    <p class="small text-body-tertiary text-center mt-4 mb-0">
        Solo lectura · El pasado es inmutable
    </p>

</div>
</div>
</div>
