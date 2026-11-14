<div>
    @if($this->puedeCambiar)
        @if(! $abierto)
            <button type="button" wire:click="abrir" class="btn btn-link btn-sm p-0 mt-2">Cambiar referencia</button>
        @else
            <div class="border-top pt-2 mt-2">
                <div class="mb-2">
                    <label for="cambio-ref-profesional" class="form-label small fw-semibold mb-1">Nuevo profesional de referencia</label>
                    <select id="cambio-ref-profesional" wire:model="profesionalId" class="form-select form-select-sm @error('profesionalId') is-invalid @enderror">
                        <option value="">— Elige —</option>
                        @foreach($this->profesionales as $profesional)
                            <option value="{{ $profesional->id }}">{{ $profesional->nombreVisible() }}</option>
                        @endforeach
                    </select>
                    @error('profesionalId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-2">
                    <label for="cambio-ref-motivo" class="form-label small fw-semibold mb-1">Motivo</label>
                    <select id="cambio-ref-motivo" wire:model.live="motivo" class="form-select form-select-sm @error('motivo') is-invalid @enderror">
                        <option value="">— Elige —</option>
                        @foreach(\Modules\Intervencion\Enums\MotivoCambioReferencia::cases() as $opcion)
                            <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                        @endforeach
                    </select>
                    @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @if(\Modules\Intervencion\Enums\MotivoCambioReferencia::tryFrom($motivo)?->requiereTexto())
                    <div class="mb-2">
                        <label for="cambio-ref-texto" class="form-label small fw-semibold mb-1">Explica el motivo</label>
                        <textarea id="cambio-ref-texto" wire:model="motivoTexto" rows="2" class="form-control form-control-sm @error('motivoTexto') is-invalid @enderror"></textarea>
                        @error('motivoTexto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="d-flex gap-2">
                    <button type="button" wire:click="cambiar" class="btn btn-primary btn-sm">Cambiar</button>
                    <button type="button" wire:click="$set('abierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                </div>
            </div>
        @endif
    @endif
</div>
