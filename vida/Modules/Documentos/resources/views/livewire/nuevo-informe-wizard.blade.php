{{-- Asistente de informe profesional (fase 5): plantilla → redacción → vista previa → firma. Desde la Historia Social o desde un encargo (bloque E de las actuaciones del encargo). --}}
@php
    $ciudadano = $this->persona;
    $nombrePersona = $ciudadano ? trim($ciudadano->nombre.' '.$ciudadano->apellido1.' '.($ciudadano->apellido2 ?? '')) : 'la persona';
    $pasos = [1 => 'Plantilla', 2 => 'Redacción', 3 => 'Vista previa', 4 => 'Firma'];
    $informe = $this->informe;
@endphp

<div class="op-page">
    <div class="container-xl py-3">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <a href="{{ $this->volverUrl() }}" wire:navigate
                   class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1">
                    <x-heroicon-o-arrow-left class="icon-12" aria-hidden="true"/> {{ $this->volverTexto() }}
                </a>
                <h1 class="h5 fw-bold mb-0 mt-1">
                    {{ $informe ? $informe->plantilla->nombre : 'Nuevo informe' }}
                    <span class="fw-normal text-body-secondary">· {{ $nombrePersona }}</span>
                </h1>
                @if($informe)
                    <div class="small text-body-secondary">Borrador {{ $informe->identificador() }}
                        @if($guardadoEn) · Guardado a las {{ $guardadoEn }} @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Pasos --}}
        <ol class="list-group list-group-horizontal-md mb-3" aria-label="Pasos del informe">
            @foreach($pasos as $numero => $etiqueta)
                <li @class([
                        'list-group-item flex-fill d-flex align-items-center gap-2 small',
                        'active' => $paso === $numero,
                        'text-body-secondary' => $paso < $numero,
                    ])
                    @if($paso === $numero) aria-current="step" @endif>
                    <span class="badge rounded-pill text-bg-light border">{{ $numero }}</span>
                    {{ $etiqueta }}
                </li>
            @endforeach
        </ol>

        {{-- ===== Paso 1: plantilla ===== --}}
        @if($paso === 1)
            <div class="card card-body">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-semibold mb-0">Elige una plantilla</h2>
                    <div>
                        <label for="filtro-tipo" class="form-label small mb-1">Tipo de informe</label>
                        <select id="filtro-tipo" wire:model.live="filtroTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach($this->tipos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($this->plantillas->isEmpty())
                    <x-op.empty icono="document-text">No hay plantillas activas de este tipo.</x-op.empty>
                @else
                    <div class="list-group">
                        @foreach($this->plantillas as $plantilla)
                            <button type="button" wire:key="plantilla-{{ $plantilla->id }}"
                                    wire:click="elegirPlantilla({{ $plantilla->id }})"
                                    class="list-group-item list-group-item-action">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="fw-semibold">{{ $plantilla->nombre }}</span>
                                    <span class="badge rounded-pill text-bg-light border">{{ $plantilla->tipo_informe->label() }}</span>
                                </div>
                                @if($plantilla->descripcion)
                                    <div class="small text-body-secondary">{{ $plantilla->descripcion }}</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ===== Paso 2: redacción ===== --}}
        @if($paso === 2 && $informe)
            <div class="d-flex flex-column gap-3">
                @foreach($this->secciones as $seccion)
                    <section class="card card-body" wire:key="seccion-{{ $seccion['id'] }}" aria-labelledby="titulo-{{ $seccion['id'] }}">
                        <h2 id="titulo-{{ $seccion['id'] }}" class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                            {{ $seccion['titulo'] }}
                            @if($seccion['tipo'] === 'automatico')
                                <span class="badge rounded-pill text-bg-light border fw-normal">Datos de la Historia Social</span>
                            @elseif($seccion['obligatorio'])
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal">Obligatoria</span>
                            @endif
                        </h2>

                        @if($seccion['tipo'] === 'automatico')
                            @if($seccion['datos'] === [])
                                <p class="small text-body-secondary mb-0">Sin datos disponibles.</p>
                            @else
                                <dl class="row small mb-0">
                                    @foreach($seccion['datos'] as $etiqueta => $valor)
                                        <dt class="col-sm-4 fw-normal text-body-secondary">{{ $etiqueta }}</dt>
                                        <dd class="col-sm-8 mb-1">{{ $valor }}</dd>
                                    @endforeach
                                </dl>
                            @endif
                        @else
                            @if($seccion['instrucciones'])
                                <p class="small text-body-secondary mb-2">{{ $seccion['instrucciones'] }}</p>
                            @endif
                            <div wire:ignore>
                                <div class="btn-toolbar gap-1 p-1 border border-bottom-0 rounded-top bg-body-tertiary" role="toolbar" aria-label="Formato del texto de {{ $seccion['titulo'] }}">
                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onmousedown="event.preventDefault(); document.execCommand('bold')" title="Negrita"><strong>B</strong></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onmousedown="event.preventDefault(); document.execCommand('italic')" title="Cursiva"><em>I</em></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onmousedown="event.preventDefault(); document.execCommand('insertUnorderedList')" title="Lista">
                                        <x-heroicon-o-list-bullet class="icon-14" aria-hidden="true"/>
                                    </button>
                                </div>
                                <div class="form-control rounded-top-0 plan-editor-area"
                                     contenteditable="true"
                                     role="textbox"
                                     aria-multiline="true"
                                     aria-labelledby="titulo-{{ $seccion['id'] }}"
                                     data-seccion="{{ $seccion['id'] }}"
                                     x-data
                                     x-on:input.debounce.1500ms="$wire.guardarSeccion('{{ $seccion['id'] }}', $el.innerHTML)"
                                     x-on:blur="$wire.guardarSeccion('{{ $seccion['id'] }}', $el.innerHTML)">{!! $seccion['html'] !!}</div>
                            </div>
                        @endif
                    </section>
                @endforeach

                <details class="card card-body small">
                    <summary class="fw-semibold">Variables que puedes escribir en el texto</summary>
                    <p class="text-body-secondary mt-2 mb-2">Escribe la variable entre dobles llaves; en el PDF se sustituye por su valor. Por ejemplo, <code>@{{nombre_ciudadano}}</code>.</p>
                    <ul class="list-unstyled row row-cols-1 row-cols-md-2 mb-0">
                        @foreach(\Modules\Documentos\Support\MergeTagsCatalogo::todos() as $clave => $etiqueta)
                            <li class="col"><code>&#123;&#123;{{ $clave }}&#125;&#125;</code> <span class="text-body-secondary">{{ $etiqueta }}</span></li>
                        @endforeach
                    </ul>
                </details>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ $this->volverUrl() }}" wire:navigate class="btn btn-outline-secondary">Seguir más tarde</a>
                    <button type="button" wire:click="irAPaso(3)" class="btn btn-primary">Vista previa</button>
                </div>
            </div>
        @endif

        {{-- ===== Paso 3: vista previa ===== --}}
        @if($paso === 3 && $informe)
            <div class="card card-body">
                @error('obligatorias')
                    <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
                @enderror
                @if($this->obligatoriasVacias !== [])
                    <div class="alert alert-warning py-2 small" role="status">
                        Faltan secciones obligatorias: {{ implode(', ', $this->obligatoriasVacias) }}.
                    </div>
                @endif

                <div class="ratio ratio-1x1 border rounded mb-3">
                    <iframe src="{{ route('documentos.informes.vista-previa', ['informe' => $informe->id, 'v' => $informe->updated_at?->timestamp]) }}"
                            title="Vista previa del informe"></iframe>
                </div>

                <div class="d-flex justify-content-between gap-2">
                    <button type="button" wire:click="irAPaso(2)" class="btn btn-outline-secondary">Volver a la redacción</button>
                    <button type="button" wire:click="irAFirma" class="btn btn-primary" @disabled($this->obligatoriasVacias !== [])>Ir a la firma</button>
                </div>
            </div>
        @endif

        {{-- ===== Paso 4: firma ===== --}}
        @if($paso === 4 && $informe)
            <div class="card card-body d-flex flex-column gap-3">
                @if($errorFirma)
                    <div class="alert alert-danger py-2 mb-0" role="alert">{{ $errorFirma }}</div>
                @endif

                <section aria-labelledby="titulo-firma-externa">
                    <h2 id="titulo-firma-externa" class="h6 fw-semibold">Firmar con tu certificado</h2>
                    <ol class="small ps-3 mb-3">
                        <li>Descarga el PDF para firmar. Es el informe definitivo, con su referencia {{ $informe->identificador() }} en el pie.</li>
                        <li>Fírmalo con tu certificado de empleado público (AutoFirma de escritorio u otra herramienta de firma PDF), sin modificarlo.</li>
                        <li>Sube aquí el PDF firmado.</li>
                    </ol>
                    <a href="{{ route('documentos.informes.para-firmar', $informe->id) }}" class="btn btn-outline-primary btn-sm mb-3">
                        <x-heroicon-o-arrow-down-tray class="icon-14" aria-hidden="true"/> Descargar PDF para firmar
                    </a>

                    <form wire:submit="subirFirmado" class="d-flex flex-column gap-2">
                        <div>
                            <label for="pdf-firmado" class="form-label small mb-1">PDF firmado</label>
                            <input id="pdf-firmado" type="file" accept="application/pdf" wire:model="pdfFirmado"
                                   @class(['form-control', 'is-invalid' => $errors->has('pdfFirmado')])>
                            @error('pdfFirmado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <button type="button" wire:click="irAPaso(3)" class="btn btn-outline-secondary">Volver a la vista previa</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="subirFirmado,pdfFirmado">
                                <span wire:loading.remove wire:target="subirFirmado">Subir y firmar</span>
                                <span wire:loading wire:target="subirFirmado">Comprobando la firma…</span>
                            </button>
                        </div>
                    </form>
                </section>

                {{-- AutoFirma integrada (F5-1 b): solo si está activa por configuración --}}
                @if($this->autofirmaActiva)
                    <section class="border-top pt-3" aria-labelledby="titulo-autofirma"
                             x-data="{
                                 error: null,
                                 firmando: false,
                                 async firmar() {
                                     this.error = null;
                                     if (typeof window.AutoScript === 'undefined') {
                                         this.error = 'AutoFirma no está disponible en este puesto.';
                                         return;
                                     }
                                     this.firmando = true;
                                     const respuesta = await fetch(@js(route('documentos.informes.para-firmar', $informe->id)));
                                     const datos = new Uint8Array(await respuesta.arrayBuffer());
                                     let binario = '';
                                     datos.forEach((b) => { binario += String.fromCharCode(b); });
                                     window.AutoScript.cargarAppAfirma();
                                     window.AutoScript.sign(btoa(binario), 'SHA256withRSA', 'PAdES', 'signatureSubFilter=ETSI.CAdES.detached',
                                         (firmado) => { $wire.firmarConAutoFirma(firmado).then(() => { this.firmando = false; }); },
                                         (tipo, mensaje) => { this.firmando = false; this.error = 'AutoFirma no ha firmado el documento: ' + mensaje; });
                                 }
                             }">
                        <h2 id="titulo-autofirma" class="h6 fw-semibold">Firmar con AutoFirma desde el navegador</h2>
                        <p class="small text-body-secondary">Se abrirá AutoFirma para elegir tu certificado.</p>
                        <div class="alert alert-danger py-2 small" role="alert" x-show="error" x-text="error"></div>
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="firmar()" x-bind:disabled="firmando">Firmar con AutoFirma</button>
                    </section>
                    @once
                        <script src="{{ asset('vendor/autofirma/autoscript.js') }}"></script>
                    @endonce
                @endif
            </div>
        @endif

    </div>
</div>
