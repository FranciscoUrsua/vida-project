<div class="card" id="ps-derivaciones">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h2 class="d-flex align-items-center gap-2 fw-semibold fs-6 mb-0">
            <x-heroicon-o-arrow-right-circle class="icon-16" aria-hidden="true"/>
            Derivaciones
        </h2>
        @if($this->puedeDerivar)
            <button type="button" wire:click="abrir" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                Derivar a atención especializada
            </button>
        @endif
    </div>
    <div class="card-body">
        @forelse($this->derivaciones as $derivacion)
            <div class="border-bottom py-2" wire:key="derivacion-{{ $derivacion->id }}">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge rounded-pill {{ $derivacion->estado->tono()->clasesSuave() }}">{{ $derivacion->estado->label() }}</span>
                    <span class="fw-semibold">{{ $derivacion->tipoPlan->nombre }}</span>
                    <span class="text-body-secondary small">{{ $derivacion->centroDestino->nombre }} · {{ $derivacion->created_at->format('d/m/Y') }}</span>
                    @if($derivacion->objetivo)
                        <span class="text-body-secondary small">Objetivo: {{ $derivacion->objetivo->texto }}</span>
                    @endif
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" wire:click="verHistorial({{ $derivacion->id }})" class="btn btn-link btn-sm p-0">
                            {{ $historialDe === $derivacion->id ? 'Ocultar historial' : 'Ver historial' }}
                        </button>
                        @if($this->puedeAnular($derivacion))
                            <button type="button" wire:click="prepararAnulacion({{ $derivacion->id }})" class="btn btn-link btn-sm p-0 text-danger">Anular</button>
                        @endif
                    </div>
                </div>
                <p class="small mb-0 mt-1">{{ $derivacion->motivo }}</p>
                @if($derivacion->profesionalResponsable)
                    <p class="small text-body-secondary mb-0">Responsable en destino: {{ $derivacion->profesionalResponsable->nombreVisible() }}</p>
                @endif
                @if($derivacion->motivo_rechazo)
                    <p class="small text-danger-emphasis mb-0">Motivo del rechazo: {{ $derivacion->motivo_rechazo }}</p>
                @endif

                @if($anularId === $derivacion->id)
                    <div class="mt-2">
                        <label for="motivo-anulacion-{{ $derivacion->id }}" class="form-label small fw-semibold mb-1">Motivo de la anulación</label>
                        <textarea id="motivo-anulacion-{{ $derivacion->id }}" wire:model="motivoAnulacion" rows="2"
                                  class="form-control form-control-sm @error('motivoAnulacion') is-invalid @enderror"></textarea>
                        @error('motivoAnulacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" wire:click="anular" class="btn btn-danger btn-sm">Anular derivación</button>
                            <button type="button" wire:click="$set('anularId', null)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>
                @endif

                @if($historialDe === $derivacion->id)
                    <ol class="list-unstyled small text-body-secondary mt-2 mb-0">
                        @foreach($derivacion->eventos as $evento)
                            <li wire:key="evento-derivacion-{{ $evento->id }}">
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
            <p class="text-body-secondary fst-italic mb-0">Sin derivaciones.</p>
        @endforelse
    </div>

    @if($modalAbierto)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-derivar">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 id="titulo-modal-derivar" class="modal-title fs-6">Derivar a atención especializada</h2>
                        <button wire:click="cerrar" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <div>
                            <label for="derivar-prestacion" class="form-label small fw-semibold mb-1">Prestación</label>
                            <select id="derivar-prestacion" wire:model.live="prestacionId" class="form-select form-select-sm @error('prestacionId') is-invalid @enderror">
                                <option value="">— Elige la prestación de derivación —</option>
                                @foreach($this->prestaciones as $prestacion)
                                    <option value="{{ $prestacion->id }}">{{ $prestacion->nombre }} ({{ $prestacion->tipoPlanDerivacion?->nombre }})</option>
                                @endforeach
                            </select>
                            @error('prestacionId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="derivar-centro" class="form-label small fw-semibold mb-1">Centro de destino</label>
                            <select id="derivar-centro" wire:model="centroDestinoId" class="form-select form-select-sm @error('centroDestinoId') is-invalid @enderror"
                                    @disabled(! $prestacionId)>
                                <option value="">— Elige el centro —</option>
                                @foreach($this->centrosDestino as $centro)
                                    <option value="{{ $centro->id }}">{{ $centro->nombre }}</option>
                                @endforeach
                            </select>
                            @error('centroDestinoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($prestacionId && $this->centrosDestino->isEmpty())
                                <div class="form-text">Ningún centro ofrece ese tipo de plan.</div>
                            @endif
                        </div>
                        <div>
                            <label for="derivar-objetivo" class="form-label small fw-semibold mb-1">Objetivo del plan (opcional)</label>
                            <select id="derivar-objetivo" wire:model="objetivoId" class="form-select form-select-sm">
                                <option value="">— Ninguno —</option>
                                @foreach($this->objetivos as $objetivo)
                                    <option value="{{ $objetivo->id }}">{{ $objetivo->texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="derivar-motivo" class="form-label small fw-semibold mb-1">Motivo</label>
                            <textarea id="derivar-motivo" wire:model="motivo" rows="3"
                                      class="form-control form-control-sm @error('motivo') is-invalid @enderror"
                                      placeholder="Lo verá el centro de destino"></textarea>
                            @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button wire:click="cerrar" type="button" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        <button wire:click="derivar" type="button" class="btn btn-primary btn-sm" wire:loading.attr="disabled">Derivar</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
