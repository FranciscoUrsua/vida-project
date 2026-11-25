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
                        <span class="ms-auto">
                            @include('mensajes::partials.boton-escribir-mensaje', ['tipo' => 'encargo', 'id' => $encargo->id, 'texto' => 'Escribir al TSR'])
                        </span>
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

                @if($this->puedeCitar)
                    <div class="card card-body mb-3" id="encargo-citar">
                        <h3 class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                            <x-heroicon-o-calendar-days class="icon-16" aria-hidden="true"/>
                            Citar a la persona
                        </h3>
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <div class="flex-grow-1">
                                <label for="encargo-tipo-cita" class="form-label small mb-1">Tipo de cita</label>
                                <select id="encargo-tipo-cita" wire:model="tipoCitaId" class="form-select form-select-sm @error('tipoCitaId') is-invalid @enderror">
                                    <option value="">— Elige el tipo —</option>
                                    @foreach($this->tiposCita as $id => $nombre)
                                        <option value="{{ $id }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                                @error('tipoCitaId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button type="button" wire:click="buscarHuecos" class="btn btn-outline-primary btn-sm">Buscar huecos</button>
                        </div>
                        <p class="form-text mb-0">En tu agenda, en {{ $encargo->centroDestino?->nombre }}.</p>
                        @error('citar') <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small" role="alert">{{ $message }}</div> @enderror

                        @if($huecos !== null)
                            @if($huecos === [])
                                <p class="small text-body-secondary mt-2 mb-0">No tienes huecos libres para este tipo de cita en el plazo ordinario.</p>
                            @else
                                <ul class="list-group list-group-flush mt-2">
                                    @foreach($huecos as $hueco)
                                        <li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0" wire:key="hueco-{{ $hueco['slot_id'] }}">
                                            <span class="small">{{ $hueco['fecha'] }} · {{ $hueco['hora'] }}</span>
                                            <button type="button" wire:click="citar({{ $hueco['slot_id'] }})" class="btn btn-primary btn-sm">Citar</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
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

                    @if($this->puedeSubir)
                        <form wire:submit="subir" class="border-top pt-3 mt-3 d-flex flex-column gap-2" id="encargo-subir">
                            <h4 class="small fw-semibold mb-0">Subir un documento</h4>
                            @if($errorIngesta)
                                <div class="alert alert-danger py-2 px-3 mb-0 small" role="alert">{{ $errorIngesta }}</div>
                            @endif
                            <div>
                                <label for="encargo-tipo" class="form-label small mb-1">Tipo de documento</label>
                                <select id="encargo-tipo" wire:model="tipoId" class="form-select form-select-sm @error('tipoId') is-invalid @enderror">
                                    <option value="">— Elige el tipo —</option>
                                    @foreach($this->tipos as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('tipoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="encargo-fichero" class="form-label small mb-1">Fichero</label>
                                <input id="encargo-fichero" type="file" wire:model="fichero" class="form-control form-control-sm @error('fichero') is-invalid @enderror">
                                @error('fichero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="encargo-titulo" class="form-label small mb-1">Descripción (opcional)</label>
                                <input id="encargo-titulo" type="text" wire:model="titulo" maxlength="255" class="form-control form-control-sm @error('titulo') is-invalid @enderror">
                                @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label for="encargo-fecha-emision" class="form-label small mb-1">Fecha de emisión</label>
                                    <input id="encargo-fecha-emision" type="date" wire:model="fechaEmision" class="form-control form-control-sm @error('fechaEmision') is-invalid @enderror">
                                    @error('fechaEmision') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-6">
                                    <label for="encargo-organo" class="form-label small mb-1">Órgano emisor</label>
                                    <input id="encargo-organo" type="text" wire:model="organoEmisor" class="form-control form-control-sm @error('organoEmisor') is-invalid @enderror">
                                    @error('organoEmisor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <p class="form-text mb-0">Queda en la carpeta de la persona y vinculado a este encargo; lo verá el TSR.</p>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="fichero,subir">Subir documento</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
