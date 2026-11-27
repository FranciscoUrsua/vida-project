{{-- Historial de informes de la persona (fase 5, paso 1). Vacío para quien no lee la historia. --}}
<div>
@if($this->puedeVer)
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
        <h3 class="h6 fw-bold mb-0 d-flex align-items-center gap-2">
            <x-heroicon-o-document-text class="icon-16" aria-hidden="true"/>
            Informes
        </h3>
        @if($this->puedeCrear)
            <a href="{{ route('intervencion.informes.nuevo', $historiaId) }}" wire:navigate class="btn btn-sm btn-primary">
                + Nuevo informe
            </a>
        @endif
    </div>

    @if(session('informes-ok'))
        <div class="alert alert-success py-2 small" role="status">{{ session('informes-ok') }}</div>
    @endif

    @if($this->informes->isEmpty())
        <x-op.empty icono="document-text">Esta persona no tiene informes.</x-op.empty>
    @else
        <ul class="list-group list-group-flush">
            @foreach($this->informes as $informe)
                @php
                    $esAutor = $informe->autor_id === auth()->id();
                    $urlPdf = $informe->estaFirmado() || $informe->estaAnulado() ? $this->urlPdf($informe) : null;
                @endphp
                <li class="list-group-item px-0" wire:key="informe-{{ $informe->id }}">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold">
                                {{ $informe->plantilla?->nombre ?? 'Informe' }}
                                <span class="badge rounded-pill {{ $informe->estado->tono()->clasesSuave() }} ms-1">{{ $informe->estado->label() }}</span>
                            </div>
                            <div class="small text-body-secondary">
                                {{ $informe->plantilla?->tipo_informe?->label() }} · {{ $informe->identificador() }}
                                · {{ $informe->autor?->nombreVisible() }}
                            </div>
                            <div class="small text-body-secondary">
                                Creado el {{ $informe->created_at->format('d/m/Y') }}
                                @if($informe->firmado_en) · firmado el {{ $informe->firmado_en->format('d/m/Y') }} @endif
                                @if($informe->anulado_en) · anulado el {{ $informe->anulado_en->format('d/m/Y') }} @endif
                            </div>
                            @if($informe->estaAnulado() && $informe->motivo_anulacion)
                                <div class="small">Motivo de la anulación: {{ $informe->motivo_anulacion }}</div>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @if($informe->esBorrador() && $esAutor)
                                <a href="{{ route('intervencion.informes.editar', $informe->id) }}" wire:navigate class="btn btn-sm btn-outline-primary">Continuar</a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        wire:click="eliminarBorrador({{ $informe->id }})"
                                        wire:confirm="¿Eliminar este borrador? No se puede deshacer.">Eliminar borrador</button>
                            @endif
                            @if($urlPdf)
                                <a href="{{ $urlPdf }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Ver PDF</a>
                            @endif
                            @if($informe->estaFirmado() && $esAutor)
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="abrirAnulacion({{ $informe->id }})">Anular</button>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ===== Modal de anulación ===== --}}
    @if($anularId)
        <div class="modal fade show d-block"
             wire:click.self="cerrarAnulacion"
             x-data
             x-on:keydown.escape.window="$wire.cerrarAnulacion()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-anular-titulo"
             tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content border-0 shadow" wire:submit="anular">
                    <div class="modal-header">
                        <h2 id="modal-anular-titulo" class="modal-title fs-6">Anular informe</h2>
                        <button type="button" wire:click="cerrarAnulacion" aria-label="Cerrar" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-body-secondary">El informe quedará anulado y no se podrá reabrir. El PDF firmado se conserva en la carpeta de la persona.</p>
                        <label for="motivo-anulacion" class="form-label small">Motivo</label>
                        <textarea id="motivo-anulacion" rows="3" wire:model="motivoAnulacion"
                                  @class(['form-control', 'is-invalid' => $errors->has('motivoAnulacion')])></textarea>
                        @error('motivoAnulacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="cerrarAnulacion" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        <button type="submit" class="btn btn-danger btn-sm">Anular informe</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
@endif
</div>
