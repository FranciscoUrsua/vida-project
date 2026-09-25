{{-- Tarjeta «Documentos» de la ficha del ciudadano (custodia v2, paso 7). --}}
<div class="citizen-file__card mt-3" id="ficha-documentos">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <h2 class="citizen-file__section-title mb-0">
            <x-heroicon-o-paper-clip class="icon-16" aria-hidden="true"/>
            Documentos
        </h2>
        @if($this->puedeEditar)
            <button type="button" wire:click="abrirAlta" class="btn btn-outline-primary btn-sm">
                <x-heroicon-o-arrow-up-tray class="icon-13" aria-hidden="true"/>
                Subir documento
            </button>
        @endif
    </div>

    @if(session('documentos-ok'))
        <div class="alert alert-success py-2 small" role="status">{{ session('documentos-ok') }}</div>
    @endif

    @if($this->documentos->isEmpty())
        <div class="op-empty">
            <x-heroicon-o-document-text class="op-empty__icon" aria-hidden="true"/>
            <p class="op-empty__text">No hay documentos asociados a esta persona.</p>
        </div>
    @else
        <ul class="list-group list-group-flush">
            @foreach($this->documentos as $documento)
                @php
                    $vigente = $documento->versionVigente;
                    $anteriores = $documento->versiones->where('id', '!=', $vigente?->id);
                    $abierto = in_array($documento->id, $historialAbierto, true);
                @endphp
                <li class="list-group-item px-0" wire:key="documento-{{ $documento->id }}">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold">
                                {{ $documento->tipo->nombre }}
                                @if($documento->estaCaducado())
                                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis ms-1">
                                        Caducado el {{ $documento->fecha_validez->format('d/m/Y') }}
                                    </span>
                                @elseif($documento->fecha_validez)
                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis ms-1">
                                        Válido hasta {{ $documento->fecha_validez->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                            @if($documento->titulo)
                                <div class="small">{{ $documento->titulo }}</div>
                            @endif
                            <div class="small text-body-secondary">
                                @if($vigente)
                                    Versión {{ $vigente->numero }} · subida el {{ $vigente->fecha_captura->format('d/m/Y') }}
                                    · {{ $vigente->paginas }} {{ $vigente->paginas === 1 ? 'página' : 'páginas' }}
                                @endif
                                @if($documento->fecha_emision)
                                    · emitido el {{ $documento->fecha_emision->format('d/m/Y') }}
                                @endif
                                @if($documento->organo_emisor)
                                    · {{ $documento->organo_emisor }}
                                @endif
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-1">
                            @if($vigente)
                                <a href="{{ $this->urlVer($documento) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                                    <x-heroicon-o-eye class="icon-13" aria-hidden="true"/> Ver
                                </a>
                                <a href="{{ $this->urlDescarga($documento) }}" class="btn btn-outline-secondary btn-sm">
                                    <x-heroicon-o-arrow-down-tray class="icon-13" aria-hidden="true"/> Descargar
                                </a>
                            @endif
                            @if($this->puedeEditar && $this->admiteVersiones($documento))
                                <button type="button" wire:click="abrirNuevaVersion({{ $documento->id }})" class="btn btn-outline-secondary btn-sm">
                                    <x-heroicon-o-arrow-path class="icon-13" aria-hidden="true"/> Nueva versión
                                </button>
                            @endif
                            @if($this->puedeEditar)
                                <button type="button"
                                        wire:click="desvincular({{ $documento->id }})"
                                        wire:confirm="El documento dejará de estar asociado a esta persona. No se borra y las demás personas vinculadas lo seguirán viendo. ¿Continuar?"
                                        class="btn btn-outline-danger btn-sm">
                                    <x-heroicon-o-link-slash class="icon-13" aria-hidden="true"/> Desvincular
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($anteriores->isNotEmpty())
                        <button type="button" wire:click="alternarHistorial({{ $documento->id }})"
                                class="btn btn-link btn-sm px-0 mt-1" aria-expanded="{{ $abierto ? 'true' : 'false' }}">
                            <x-heroicon-o-clock class="icon-13" aria-hidden="true"/>
                            {{ $abierto ? 'Ocultar' : 'Ver' }} versiones anteriores ({{ $anteriores->count() }})
                        </button>
                        @if($abierto)
                            <ul class="list-unstyled small text-body-secondary ms-3 mb-0">
                                @foreach($anteriores as $version)
                                    <li wire:key="version-{{ $version->id }}">
                                        Versión {{ $version->numero }} · subida el {{ $version->fecha_captura->format('d/m/Y') }}
                                        · {{ $version->estado->etiqueta() }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ===== MODAL SUBIR DOCUMENTO / NUEVA VERSIÓN ===== --}}
    @if($modalAbierto)
        <div class="modal fade show d-block"
             wire:click.self="cerrarModal"
             x-data
             x-on:keydown.escape.window="$wire.cerrarModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-documento-titulo"
             tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content border-0 shadow" wire:submit="guardar">
                    <div class="modal-header">
                        <h2 id="modal-documento-titulo" class="modal-title fs-6">
                            {{ $documentoVersionId ? 'Subir nueva versión' : 'Subir documento' }}
                        </h2>
                        <button type="button" wire:click="cerrarModal" aria-label="Cerrar" class="btn-close"></button>
                    </div>

                    <div class="modal-body d-flex flex-column gap-3">
                        @if($errorIngesta)
                            <div class="alert alert-danger py-2 mb-0" role="alert">{{ $errorIngesta }}</div>
                        @endif

                        @if($documentoVersionId)
                            <p class="small text-body-secondary mb-0">
                                La nueva versión sustituye a la actual para todas las personas asociadas al documento.
                            </p>
                        @else
                            <div>
                                <label class="form-label" for="doc-tipo">Tipo de documento</label>
                                <select id="doc-tipo" wire:model.live="tipoId" class="form-select form-select-sm @error('tipoId') is-invalid @enderror">
                                    <option value="">Elige un tipo…</option>
                                    @foreach($this->tipos as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('tipoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="form-label" for="doc-fichero">Fichero</label>
                            <input type="file" id="doc-fichero" wire:model="fichero"
                                   class="form-control form-control-sm @error('fichero') is-invalid @enderror">
                            <div class="form-text">PDF, imagen (JPG, PNG, HEIC) o documento ODT o DOCX. Se guarda convertido a PDF.</div>
                            <div wire:loading wire:target="fichero" class="form-text">Cargando el fichero…</div>
                            @error('fichero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="doc-canal">Cómo se ha recibido</label>
                            <select id="doc-canal" wire:model="canal" class="form-select form-select-sm">
                                <option value="presencial">Entregado en persona</option>
                                <option value="escaneo">Escaneado en el centro</option>
                            </select>
                        </div>

                        @unless($documentoVersionId)
                            <div>
                                <label class="form-label" for="doc-titulo">Descripción <span class="text-body-secondary">(opcional)</span></label>
                                <input type="text" id="doc-titulo" wire:model="titulo" maxlength="255"
                                       class="form-control form-control-sm @error('titulo') is-invalid @enderror">
                                @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-5">
                                    <label class="form-label" for="doc-fecha">
                                        Fecha de emisión
                                        @unless($this->exige('fecha_emision'))<span class="text-body-secondary">(opcional)</span>@endunless
                                    </label>
                                    <input type="date" id="doc-fecha" wire:model="fechaEmision" max="{{ now()->toDateString() }}"
                                           class="form-control form-control-sm @error('fechaEmision') is-invalid @enderror">
                                    @error('fechaEmision') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-7">
                                    <label class="form-label" for="doc-organo">
                                        Órgano emisor
                                        @unless($this->exige('organo_emisor'))<span class="text-body-secondary">(opcional)</span>@endunless
                                    </label>
                                    <input type="text" id="doc-organo" wire:model="organoEmisor" maxlength="255"
                                           class="form-control form-control-sm @error('organoEmisor') is-invalid @enderror">
                                    @error('organoEmisor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            @foreach($this->metadatosAdicionales() as $clave)
                                <div wire:key="metadato-{{ $clave }}">
                                    <label class="form-label" for="doc-meta-{{ $clave }}">{{ ucfirst(str_replace('_', ' ', $clave)) }}</label>
                                    <input type="text" id="doc-meta-{{ $clave }}" wire:model="metadatos.{{ $clave }}"
                                           class="form-control form-control-sm @error('metadatos.'.$clave) is-invalid @enderror">
                                    @error('metadatos.'.$clave) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endforeach

                            @if($this->otrosMiembros->isNotEmpty())
                                <fieldset>
                                    <legend class="form-label fs-6 mb-1">Asociar también a otros miembros de la unidad de convivencia</legend>
                                    @foreach($this->otrosMiembros as $miembro)
                                        <div class="form-check" wire:key="miembro-{{ $miembro->ciudadano_id }}">
                                            <input type="checkbox" class="form-check-input" id="doc-miembro-{{ $miembro->ciudadano_id }}"
                                                   value="{{ $miembro->ciudadano_id }}" wire:model="otrosVinculados">
                                            <label class="form-check-label" for="doc-miembro-{{ $miembro->ciudadano_id }}">
                                                {{ $miembro->ciudadano?->nombre_completo ?? '—' }}
                                            </label>
                                        </div>
                                    @endforeach
                                </fieldset>
                            @endif
                        @endunless
                    </div>

                    <div class="modal-footer">
                        <button type="button" wire:click="cerrarModal" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="guardar,fichero">
                            <span wire:loading.remove wire:target="guardar">
                                <x-heroicon-o-check class="icon-13" aria-hidden="true"/> Guardar
                            </span>
                            <span wire:loading wire:target="guardar">Comprobando y guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
