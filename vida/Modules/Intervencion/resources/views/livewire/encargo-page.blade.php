{{-- Detalle de un encargo profesional para el profesional asignado (C7, 2026-11-encargo-profesional.md) --}}
@php($encargo = $this->encargo)
<div class="op-page">
    <section class="p-3">
        <a href="{{ route('intervencion.encargos.index') }}" wire:navigate class="btn btn-link btn-sm px-0 mb-2 d-inline-flex align-items-center gap-1">
            <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/>
            Mis derivaciones
        </a>

        @if($aviso)
            <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                <x-heroicon-o-check-circle class="icon-20 flex-shrink-0" aria-hidden="true"/>
                <span>{{ $aviso }}</span>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card card-body mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <h2 class="h5 fw-semibold mb-0">
                            <a href="{{ route('ciudadania.ciudadano.ficha', $this->persona->id) }}" wire:navigate>{{ $this->persona->nombre_completo }}</a>
                        </h2>
                        <span class="badge {{ $encargo->estado->tono()->clasesSuave() }}">{{ $encargo->estado->label() }}</span>
                    </div>

                    <h3 class="h6 fw-semibold mb-1">Motivo y contexto del encargo</h3>
                    <p class="mb-0 text-break">{!! nl2br(e($encargo->motivo)) !!}</p>
                </div>

                @if($encargo->estado === \Modules\Intervencion\Enums\EstadoDerivacion::Cerrada)
                    <div class="card card-body mb-3" id="encargo-resultado">
                        <h3 class="h6 fw-semibold mb-1">Resultado</h3>
                        <p class="mb-2 text-break">{!! nl2br(e($encargo->resultado)) !!}</p>
                        <p class="small text-body-secondary mb-0">
                            Cerrado el {{ $encargo->cerrada_en?->format('d/m/Y H:i') }} por {{ $encargo->cerradaPor?->nombreVisible() }}.
                        </p>
                    </div>
                @elseif($this->puedeCerrar)
                    <div class="card card-body mb-3" id="encargo-cerrar">
                        <h3 class="h6 fw-semibold mb-2">Registrar el resultado y cerrar</h3>
                        <label for="encargo-resultado-texto" class="form-label small">Resultado de tu actuación</label>
                        <textarea id="encargo-resultado-texto" wire:model="resultado" rows="5"
                                  class="form-control @error('resultado') is-invalid @enderror"></textarea>
                        @error('resultado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <p class="form-text">El resultado queda en la Historia Social de la persona, visible para los profesionales de intervención.</p>
                        <div>
                            <button type="button" wire:click="cerrar" wire:confirm="¿Cerrar el encargo con este resultado? Después no se puede modificar." class="btn btn-primary btn-sm">Cerrar el encargo</button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card card-body mb-3">
                    <dl class="small mb-0">
                        <dt class="text-body-secondary fw-normal">Centro</dt>
                        <dd class="mb-2 fw-semibold">{{ $encargo->centroDestino?->nombre }}</dd>
                        <dt class="text-body-secondary fw-normal">Encarga</dt>
                        <dd class="mb-2">{{ $encargo->derivadaPor?->nombreVisible() }}</dd>
                        <dt class="text-body-secondary fw-normal">Fecha del encargo</dt>
                        <dd class="mb-2">{{ $encargo->created_at->format('d/m/Y') }}</dd>
                        <dt class="text-body-secondary fw-normal">Asignado</dt>
                        <dd class="mb-0">{{ $encargo->resuelta_en?->format('d/m/Y') ?? '—' }}</dd>
                    </dl>
                </div>

                <div class="card card-body mb-3" id="encargo-documentos">
                    <h3 class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                        <x-heroicon-o-paper-clip class="icon-16" aria-hidden="true"/>
                        Documentos del encargo
                    </h3>
                    @if($this->documentos->isEmpty())
                        <p class="small text-body-secondary mb-0">No hay documentos adjuntos.</p>
                    @else
                        <ul class="list-unstyled small mb-0">
                            @foreach($this->documentos as $documento)
                                <li class="mb-1" wire:key="encargo-doc-{{ $documento->id }}">
                                    @if($this->puedeVerDocumento($documento))
                                        <a href="{{ $this->urlVer($documento) }}" target="_blank" rel="noopener">{{ $documento->titulo ?: $documento->tipo?->nombre }}</a>
                                    @else
                                        {{ $documento->titulo ?: $documento->tipo?->nombre }}
                                    @endif
                                    <span class="d-block text-body-secondary">{{ $documento->tipo?->nombre }} · {{ $documento->created_at->format('d/m/Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
