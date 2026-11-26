<div class="card" id="ps-encargos">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h2 class="d-flex align-items-center gap-2 fw-semibold fs-6 mb-0">
            <x-heroicon-o-briefcase class="icon-16" aria-hidden="true"/>
            Encargos profesionales
        </h2>
        @if($this->puedeEncargar)
            <button type="button" wire:click="abrir" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                Encargo profesional
            </button>
        @endif
    </div>
    <div class="card-body">
        @forelse($this->encargos as $encargo)
            <div class="border-bottom py-2" wire:key="encargo-{{ $encargo->id }}">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge rounded-pill {{ $encargo->estado->tono()->clasesSuave() }}">{{ $encargo->estado->label() }}</span>
                    <span class="fw-semibold">{{ $encargo->centroDestino->nombre }}</span>
                    @if($encargo->area)
                        <span class="badge rounded-pill text-bg-light border">{{ $encargo->area->nombre }}</span>
                    @endif
                    <span class="text-body-secondary small">{{ $encargo->derivadaPor?->nombreVisible() ?? '—' }} · {{ $encargo->created_at->format('d/m/Y') }}</span>
                    @if($encargo->objetivo)
                        <span class="text-body-secondary small">Objetivo: {{ $encargo->objetivo->texto }}</span>
                    @endif
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" wire:click="verHistorial({{ $encargo->id }})" class="btn btn-link btn-sm p-0">
                            {{ $historialDe === $encargo->id ? 'Ocultar historial' : 'Ver historial' }}
                        </button>
                        @if($this->puedeAnular($encargo))
                            <button type="button" wire:click="prepararAnulacion({{ $encargo->id }})" class="btn btn-link btn-sm p-0 text-danger">Anular</button>
                        @endif
                    </div>
                </div>
                <p class="small mb-0 mt-1">{{ $encargo->motivo }}</p>
                @if($encargo->profesionalResponsable)
                    <p class="small text-body-secondary mb-0">Profesional: {{ $encargo->profesionalResponsable->nombreVisible() }}</p>
                @endif
                @if($encargo->motivo_rechazo)
                    <p class="small text-danger-emphasis mb-0">Motivo del rechazo: {{ $encargo->motivo_rechazo }}</p>
                @endif
                @if($encargo->estado === \Modules\Intervencion\Enums\EstadoDerivacion::Cerrada)
                    <div class="small bg-body-tertiary rounded p-2 mt-1">
                        <span class="fw-semibold">Resultado ({{ $encargo->cerrada_en?->format('d/m/Y') }}):</span>
                        <span class="text-break">{!! nl2br(e($encargo->resultado)) !!}</span>
                    </div>
                @endif

                {{-- Actuaciones del profesional desde el encargo (EA-12), sin sus anotaciones privadas --}}
                @if($actuacionesEncargo = $this->actuaciones->get($encargo->id))
                    <ul class="list-unstyled small mt-1 mb-0 border-start ps-2">
                        @foreach($actuacionesEncargo as $actuacion)
                            <li class="mb-1" wire:key="encargo-{{ $encargo->id }}-actuacion-{{ $actuacion->id }}">
                                <a href="{{ $this->urlApunte($actuacion) }}" wire:navigate class="fw-semibold">{{ $actuacion->tipo->label() }}</a>
                                <span class="text-body-secondary">· {{ $actuacion->fecha->format('d/m/Y') }} · {{ $actuacion->autor?->name }}</span>
                                @if($actuacion->contenido)
                                    <span class="d-block text-truncate">{{ $actuacion->contenido }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($anularId === $encargo->id)
                    <div class="mt-2">
                        <label for="motivo-anulacion-encargo-{{ $encargo->id }}" class="form-label small fw-semibold mb-1">Motivo de la anulación</label>
                        <textarea id="motivo-anulacion-encargo-{{ $encargo->id }}" wire:model="motivoAnulacion" rows="2"
                                  class="form-control form-control-sm @error('motivoAnulacion') is-invalid @enderror"></textarea>
                        @error('motivoAnulacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" wire:click="anular" class="btn btn-danger btn-sm">Anular encargo</button>
                            <button type="button" wire:click="$set('anularId', null)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>
                @endif

                @if($historialDe === $encargo->id)
                    <ol class="list-unstyled small text-body-secondary mt-2 mb-0">
                        @foreach($encargo->eventos as $evento)
                            <li wire:key="evento-encargo-{{ $evento->id }}">
                                {{ $evento->created_at->format('d/m/Y H:i') }} ·
                                {{ $evento->estado_despues->label() }} ·
                                {{ $evento->actor?->nombreVisible() ?? '—' }}
                                @if($evento->motivo) · {{ $evento->motivo }} @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @empty
            <p class="text-body-secondary fst-italic mb-0">Sin encargos profesionales.</p>
        @endforelse
    </div>

    @if($modalAbierto)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-encargo">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 id="titulo-modal-encargo" class="modal-title fs-6">Encargo profesional</h2>
                        <button wire:click="cerrar" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <div>
                            <label for="encargo-centro" class="form-label small fw-semibold mb-1">Centro de destino</label>
                            <select id="encargo-centro" wire:model="centroDestinoId" class="form-select form-select-sm @error('centroDestinoId') is-invalid @enderror">
                                <option value="">— Elige el centro —</option>
                                @foreach($this->centrosDestino as $centro)
                                    <option value="{{ $centro->id }}">{{ $centro->nombre }}</option>
                                @endforeach
                            </select>
                            @error('centroDestinoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($this->centrosDestino->isEmpty())
                                <div class="form-text">Ningún centro tiene profesionales que reciban encargos.</div>
                            @endif
                        </div>
                        <div>
                            <label for="encargo-area" class="form-label small fw-semibold mb-1">Área</label>
                            <select id="encargo-area" wire:model="areaId" class="form-select form-select-sm @error('areaId') is-invalid @enderror">
                                <option value="">— Elige el área —</option>
                                @foreach($this->areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                                @endforeach
                            </select>
                            @error('areaId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Decide las fichas, escalas e informes que tendrá el profesional.</div>
                        </div>
                        @if($planId !== null)
                            <div>
                                <label for="encargo-objetivo" class="form-label small fw-semibold mb-1">Objetivo del plan (opcional)</label>
                                <select id="encargo-objetivo" wire:model="objetivoId" class="form-select form-select-sm">
                                    <option value="">— Ninguno —</option>
                                    @foreach($this->objetivos as $objetivo)
                                        <option value="{{ $objetivo->id }}">{{ $objetivo->texto }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="encargo-motivo" class="form-label small fw-semibold mb-1">Motivo y contexto</label>
                            <textarea id="encargo-motivo" wire:model="motivo" rows="4"
                                      class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                      placeholder="Lo verá el profesional que haga el encargo"></textarea>
                            @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <fieldset>
                            <legend class="form-label small fw-semibold mb-1">Documentos que se adjuntan (opcional)</legend>
                            @forelse($this->documentosCarpeta as $documento)
                                <div class="form-check" wire:key="encargo-doc-{{ $documento->id }}">
                                    <input class="form-check-input" type="checkbox" id="encargo-doc-{{ $documento->id }}" value="{{ $documento->id }}" wire:model="documentoIds">
                                    <label class="form-check-label small" for="encargo-doc-{{ $documento->id }}">
                                        {{ $documento->titulo }}
                                        <span class="text-body-secondary">· {{ $documento->tipo?->nombre }} · {{ $documento->created_at->format('d/m/Y') }}</span>
                                    </label>
                                </div>
                            @empty
                                <p class="small text-body-secondary fst-italic mb-0">No hay documentos en la carpeta de la persona.</p>
                            @endforelse
                            <div class="form-text">El profesional solo verá los documentos que adjuntes.</div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <button wire:click="cerrar" type="button" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        <button wire:click="encargar" type="button" class="btn btn-primary btn-sm" wire:loading.attr="disabled">Encargar</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
