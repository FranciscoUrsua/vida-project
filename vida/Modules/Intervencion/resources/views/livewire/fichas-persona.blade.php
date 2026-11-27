{{-- Fichas de la persona en la ficha del ciudadano (FichasPersona, bloque D de la ficha sociodemográfica) --}}
<div>
    @if(session('fichas-persona-ok'))
        <div class="alert alert-success small py-2" role="status">{{ session('fichas-persona-ok') }}</div>
    @endif

    @foreach($this->tarjetas as $tarjeta)
        @php
            $tipo = $tarjeta['tipo'];
            $vigente = $tarjeta['vigente'];
            $borrador = $tarjeta['borrador'];
            $anteriores = $tarjeta['anteriores'];
            $abierto = $historialAbierto[$tipo->id] ?? false;
        @endphp
        <div class="card mb-3" id="ficha-persona-{{ $tipo->id }}" wire:key="ficha-persona-{{ $tipo->id }}">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <x-heroicon-o-identification class="icon-14" aria-hidden="true"/>
                <h2 class="h6 fw-semibold mb-0">{{ $tipo->nombre }}</h2>
                @if($this->puedeRegistrar && $tipo->activo)
                    <a href="{{ route('intervencion.fichas-persona.registrar', ['ciudadano' => $ciudadanoId, 'tipoFicha' => $tipo->id]) }}"
                       wire:navigate class="btn btn-outline-primary btn-sm ms-auto">
                        @if($borrador)
                            Continuar borrador
                        @elseif($vigente)
                            Actualizar
                        @else
                            Registrar
                        @endif
                    </a>
                @endif
            </div>

            <div class="card-body">
                @if($vigente)
                    @php $respondidos = collect($vigente->camposConValor())->filter(fn ($c) => $c['no_contesta'] || $c['texto'] !== ''); @endphp
                    @if($respondidos->isEmpty())
                        <p class="small text-body-secondary mb-2">Registrada sin respuestas.</p>
                    @else
                        <dl class="row small mb-2">
                            @foreach($respondidos as $campo)
                                <dt class="col-sm-5 fw-normal text-body-secondary">{{ $campo['etiqueta'] }}</dt>
                                <dd class="col-sm-7 mb-1">
                                    @if($campo['no_contesta'])
                                        <span class="fst-italic text-body-secondary">No desea contestar</span>
                                    @else
                                        {{ $campo['texto'] }}
                                        @if($campo['unidad'] && ! in_array($campo['tipo'], ['booleano', 'fecha'], true))
                                            <span class="text-body-secondary">{{ $campo['unidad'] }}</span>
                                        @endif
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    @endif
                    <p class="small text-body-secondary mb-0">
                        Actualizado el {{ $vigente->created_at->translatedFormat('j M Y') }}
                        · {{ $vigente->profesional?->nombreVisible() ?? '—' }}
                        · <a href="{{ route('intervencion.fichas-persona.ver', ['ciudadano' => $ciudadanoId, 'ficha' => $vigente->id]) }}" wire:navigate>Ver completa</a>
                    </p>
                @else
                    <p class="small text-body-secondary mb-0">Sin datos registrados.</p>
                @endif

                @if($borrador)
                    <p class="small mb-0 mt-2">
                        <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                        Sin completar, del {{ $borrador->created_at->translatedFormat('j M Y') }} · {{ $borrador->profesional?->nombreVisible() ?? '—' }}
                        · <a href="{{ route('intervencion.fichas-persona.ver', ['ciudadano' => $ciudadanoId, 'ficha' => $borrador->id]) }}" wire:navigate>Ver</a>
                    </p>
                @endif

                @if($anteriores !== [])
                    <button type="button" class="btn btn-link btn-sm p-0 mt-2" wire:click="alternarHistorial({{ $tipo->id }})"
                            aria-expanded="{{ $abierto ? 'true' : 'false' }}">
                        {{ $abierto ? 'Ocultar historial' : 'Historial ('.count($anteriores).')' }}
                    </button>
                    @if($abierto)
                        <ul class="list-group list-group-flush small mt-2">
                            @foreach($anteriores as $anterior)
                                <li class="list-group-item px-0 d-flex align-items-center gap-2" wire:key="ficha-persona-anterior-{{ $anterior->id }}">
                                    <span>{{ $anterior->created_at->translatedFormat('j M Y') }} · {{ $anterior->profesional?->nombreVisible() ?? '—' }}</span>
                                    @unless($anterior->completada)
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Borrador</span>
                                    @endunless
                                    <a href="{{ route('intervencion.fichas-persona.ver', ['ciudadano' => $ciudadanoId, 'ficha' => $anterior->id]) }}"
                                       wire:navigate class="ms-auto">Ver</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
</div>
