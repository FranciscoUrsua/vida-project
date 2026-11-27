{{-- PISO firmado del plan (fase 5, paso 4). Solo en el plan principal. --}}
<div>
@if($this->plan?->esPrincipal())
    <div class="card card-body mb-3" id="piso-firmado">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-0">
                <x-heroicon-o-document-check class="icon-16" aria-hidden="true"/>
                PISO firmado
            </h2>
            @if($this->puedeSubir)
                <button type="button" wire:click="abrir" class="btn btn-outline-primary btn-sm">
                    <x-heroicon-o-arrow-up-tray class="icon-14" aria-hidden="true"/>
                    {{ $this->activo ? 'Sustituir PISO firmado' : 'Subir PISO firmado' }}
                </button>
            @endif
        </div>

        @if(session('piso-ok'))
            <div class="alert alert-success py-2 small mt-2 mb-0" role="status">{{ session('piso-ok') }}</div>
        @endif

        @if($this->activo)
            @php $url = $this->urlDocumento(); @endphp
            <p class="small mb-0 mt-2">
                PISO firmado el {{ $this->activo->created_at->format('d/m/Y') }},
                subido por {{ $this->activo->subidoPor?->nombreVisible() }}.
                @if($url)
                    <a href="{{ $url }}" target="_blank" rel="noopener">Ver documento</a>
                @endif
            </p>
            @if($this->activo->observaciones)
                <p class="small text-body-secondary mb-0">{{ $this->activo->observaciones }}</p>
            @endif
        @else
            <p class="small text-body-secondary mb-0 mt-2">Aún no se ha subido el PISO firmado por el profesional y la persona.</p>
        @endif
    </div>

    @if($modalAbierto)
        <div class="modal fade show d-block"
             wire:click.self="cerrar"
             x-data
             x-on:keydown.escape.window="$wire.cerrar()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-piso-titulo"
             tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content border-0 shadow" wire:submit="subir">
                    <div class="modal-header">
                        <h2 id="modal-piso-titulo" class="modal-title fs-6">{{ $this->activo ? 'Sustituir PISO firmado' : 'Subir PISO firmado' }}</h2>
                        <button type="button" wire:click="cerrar" aria-label="Cerrar" class="btn-close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if($errorSubida)
                            <div class="alert alert-danger py-2 mb-0" role="alert">{{ $errorSubida }}</div>
                        @endif
                        <p class="small text-body-secondary mb-0">
                            Escaneo del PISO impreso con la firma manuscrita del profesional y de la persona.
                            @if($this->activo) El anterior se conserva en la carpeta como versión anterior. @endif
                        </p>
                        <div>
                            <label for="piso-fichero" class="form-label small mb-1">Escaneo (PDF o imagen)</label>
                            <input id="piso-fichero" type="file" wire:model="fichero" accept="application/pdf,image/jpeg,image/png,image/heic"
                                   @class(['form-control', 'is-invalid' => $errors->has('fichero')])>
                            @error('fichero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="piso-observaciones" class="form-label small mb-1">Observaciones (opcional)</label>
                            <textarea id="piso-observaciones" rows="2" wire:model="observaciones" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="cerrar" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="subir,fichero">
                            <span wire:loading.remove wire:target="subir">Guardar</span>
                            <span wire:loading wire:target="subir">Comprobando y guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
@endif
</div>
