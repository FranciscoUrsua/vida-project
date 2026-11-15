{{-- Solicitud de prestación (fase 3 bis, paso 5.2). Tramitación no ve aquí nada de la Historia Social (RN-10). --}}
@php
    $s = $this->solicitud;
    $p = $this->puede;
    $persona = $this->persona;
    $documento = $persona->documentoVigente;
@endphp
<div class="op-page">
<div class="row g-3 mx-0 p-3 p-lg-4">

    <div class="col-12 d-flex flex-wrap align-items-center gap-2">
        <h1 class="h5 mb-0">{{ $s->prestacion->nombre }}</h1>
        <span class="badge rounded-pill {{ $s->estado->tono()->clasesSuave() }}">{{ $s->estado->label() }}</span>
        <span class="badge text-bg-light border">{{ $s->via->label() }}</span>
        @if($p['verHistoria'])
            <a href="{{ route('intervencion.ciudadano.show', $s->historia_id) }}" wire:navigate class="ms-auto small">Volver a la Historia Social</a>
        @endif
    </div>

    @if($mensaje)
        <div class="col-12"><div class="alert alert-success py-2 mb-0" role="status">{{ $mensaje }}</div></div>
    @endif
    @error('accion')
        <div class="col-12"><div class="alert alert-danger py-2 mb-0" role="alert">{{ $message }}</div></div>
    @enderror

    {{-- Columna principal --}}
    <div class="col-lg-8 d-flex flex-column gap-3">

        {{-- Persona: identificación y contacto --}}
        <section class="card" aria-labelledby="titulo-persona">
            <div class="card-body">
                <h2 id="titulo-persona" class="h6 fw-semibold mb-2">Persona</h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-3 fw-normal text-body-secondary">Nombre</dt>
                    <dd class="col-sm-9">{{ $persona->nombre_completo }}</dd>
                    @if($documento)
                        <dt class="col-sm-3 fw-normal text-body-secondary">Documento</dt>
                        <dd class="col-sm-9 font-monospace">{{ strtoupper($documento->tipo) }} {{ $documento->valor }}</dd>
                    @endif
                    @if($persona->telefono)
                        <dt class="col-sm-3 fw-normal text-body-secondary">Teléfono</dt>
                        <dd class="col-sm-9">{{ $persona->telefono }}</dd>
                    @endif
                    @if($persona->email)
                        <dt class="col-sm-3 fw-normal text-body-secondary">Correo</dt>
                        <dd class="col-sm-9">{{ $persona->email }}</dd>
                    @endif
                    @if($persona->direccion_texto)
                        <dt class="col-sm-3 fw-normal text-body-secondary">Dirección</dt>
                        <dd class="col-sm-9">{{ $persona->direccion_texto }}</dd>
                    @endif
                </dl>
                @if($s->personasAfectadas->isNotEmpty())
                    <p class="small mb-0 mt-2"><span class="text-body-secondary">Otras personas afectadas:</span>
                        {{ $s->personasAfectadas->map(fn ($c) => $c->nombre_completo)->join(', ') }}</p>
                @endif
                @if($p['editar'] && $this->miembrosUc->isNotEmpty())
                    <div class="d-flex gap-2 mt-2">
                        <label for="persona-afectada" class="visually-hidden">Persona afectada</label>
                        <select id="persona-afectada" wire:model="personaId" class="form-select form-select-sm w-auto">
                            <option value="">Añadir persona de la unidad de convivencia…</option>
                            @foreach($this->miembrosUc as $miembro)
                                <option value="{{ $miembro->id }}">{{ $miembro->nombre_completo }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="anadirPersona" class="btn btn-outline-secondary btn-sm">Añadir</button>
                    </div>
                @endif
            </div>
        </section>

        {{-- Parámetros --}}
        @if($s->ficha)
            <section class="card" aria-labelledby="titulo-parametros">
                <div class="card-body">
                    <h2 id="titulo-parametros" class="h6 fw-semibold mb-3">Datos de la solicitud</h2>
                    <x-ficha.campos :campos="$s->ficha->schema_snapshot['campos'] ?? []" prefijo="datos" :deshabilitado="! $p['editar']" />
                    @if($p['editar'])
                        <button type="button" wire:click="guardarDatos" class="btn btn-outline-primary btn-sm">Guardar datos</button>
                    @endif
                </div>
            </section>
        @endif

        {{-- Documentos --}}
        <section class="card" aria-labelledby="titulo-documentos">
            <div class="card-body">
                <h2 id="titulo-documentos" class="h6 fw-semibold mb-2">Documentos</h2>
                @if($this->documentosExigidos->isNotEmpty())
                    <ul class="list-unstyled small mb-3">
                        @foreach($this->documentosExigidos as $exigido)
                            <li class="d-flex align-items-center gap-2">
                                @if($exigido['aportado'])
                                    <x-heroicon-o-check-circle class="icon-16 text-success" aria-hidden="true"/>
                                @else
                                    <x-heroicon-o-clock class="icon-16 text-body-secondary" aria-hidden="true"/>
                                @endif
                                {{ $exigido['nombre'] }}
                                <span class="text-body-secondary">· {{ $exigido['obligatorio'] ? 'obligatorio' : 'opcional' }} · {{ $exigido['aportado'] ? 'aportado' : 'pendiente' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @forelse($this->documentosVinculados as $doc)
                    <div class="d-flex align-items-center gap-2 small border-top py-1" wire:key="doc-{{ $doc->id }}">
                        <span>{{ $doc->tipo?->nombre }}</span>
                        <span class="text-body-secondary">{{ $doc->titulo }}</span>
                        @if($p['documentos'] && $s->estado !== \Modules\Prestaciones\Enums\EstadoSolicitudPrestacion::Enviada)
                            <button type="button" wire:click="desvincularDocumento({{ $doc->id }})" class="btn btn-link btn-sm p-0 ms-auto">Quitar</button>
                        @endif
                    </div>
                @empty
                    <p class="text-body-secondary small mb-0">Sin documentos vinculados.</p>
                @endforelse

                @if($p['documentos'])
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label for="doc-carpeta" class="form-label small fw-semibold mb-1">Desde la carpeta de la persona</label>
                            <div class="d-flex gap-2">
                                <select id="doc-carpeta" wire:model="documentoId" class="form-select form-select-sm @error('documentoId') is-invalid @enderror">
                                    <option value="">Elige…</option>
                                    @foreach($this->documentosCarpeta as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->tipo?->nombre }} · {{ $doc->titulo ?? $doc->created_at?->format('d/m/Y') }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="vincularDocumento" class="btn btn-outline-secondary btn-sm">Vincular</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="doc-subir" class="form-label small fw-semibold mb-1">Subir uno nuevo</label>
                            <select wire:model="tipoDocumentalId" class="form-select form-select-sm mb-1 @error('tipoDocumentalId') is-invalid @enderror" aria-label="Tipo documental">
                                <option value="">Tipo documental…</option>
                                @foreach($this->tiposDocumentales as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-2">
                                <input id="doc-subir" type="file" wire:model="fichero" class="form-control form-control-sm @error('fichero') is-invalid @enderror">
                                <button type="button" wire:click="subirDocumento" class="btn btn-outline-secondary btn-sm">Subir</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- Resolución --}}
        @if($s->resolucion)
            <section class="card" aria-labelledby="titulo-resolucion">
                <div class="card-body">
                    <h2 id="titulo-resolucion" class="h6 fw-semibold mb-2">Resolución · {{ $s->resolucion->sentido->label() }} · {{ $s->resolucion->fecha->format('d/m/Y') }}</h2>
                    <ul class="list-unstyled small mb-0">
                        @foreach($s->resolucion->lineas as $linea)
                            <li>
                                <strong>{{ $linea->prestacion->nombre }}</strong>:
                                {{ $linea->concedida ? 'concedida' : 'denegada' }}
                                @if($linea->intensidad) · {{ $linea->intensidad }} @endif
                                @if($linea->importe) · {{ number_format((float) $linea->importe, 2, ',', '.') }} € @endif
                                @if(! $linea->concedida && $linea->motivo_denegacion) · {{ $linea->motivo_denegacion }} @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- Historial --}}
        <section class="card" aria-labelledby="titulo-historial">
            <div class="card-body">
                <h2 id="titulo-historial" class="h6 fw-semibold mb-2">Historial</h2>
                <ol class="list-unstyled small mb-0">
                    @foreach($s->eventos as $evento)
                        <li class="border-top py-1" wire:key="evento-{{ $evento->id }}">
                            <span class="text-body-secondary">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                            · {{ $evento->accion->label() }}
                            @if($evento->detalle['texto'] ?? null) · {{ $evento->detalle['texto'] }} @endif
                            @if($evento->detalle['descripcion'] ?? null) · {{ $evento->detalle['descripcion'] }} @endif
                            @if($evento->detalle['justificacion'] ?? null) · Justificación: {{ $evento->detalle['justificacion'] }} @endif
                            @if($evento->nota) · {{ $evento->nota }} @endif
                            <span class="text-body-secondary">· {{ $evento->usuario?->nombreVisible() ?? $evento->origen->label() }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    </div>

    {{-- Columna lateral: estado y acciones --}}
    <div class="col-lg-4 d-flex flex-column gap-3">
        <section class="card" aria-labelledby="titulo-tramite">
            <div class="card-body small">
                <h2 id="titulo-tramite" class="h6 fw-semibold mb-2">Tramitación</h2>
                <dl class="mb-0">
                    <dt class="fw-normal text-body-secondary">Unidad tramitadora</dt>
                    <dd>{{ $s->unidadTramitadora?->nombre ?? 'Se fija al pasar a la bandeja' }}</dd>
                    <dt class="fw-normal text-body-secondary">Responsable</dt>
                    <dd>{{ $s->responsable?->nombreVisible() ?? 'Nadie la ha tomado' }}</dd>
                    @if($s->sugerida)
                        <dt class="fw-normal text-body-secondary">Sugerida</dt>
                        <dd>{{ $s->sugerida->nombreVisible() }}</dd>
                    @endif
                    @if($s->numero_expediente)
                        <dt class="fw-normal text-body-secondary">Expediente</dt>
                        <dd class="font-monospace">{{ $s->numero_expediente }} · {{ $s->fecha_envio?->format('d/m/Y') }}</dd>
                    @endif
                    @if($s->estado_externo_normalizado || $s->estado_externo_texto)
                        <dt class="fw-normal text-body-secondary">Estado del expediente</dt>
                        <dd>{{ $s->estado_externo_texto ?? $s->estado_externo_normalizado }}
                            <span class="text-body-secondary d-block">Actualizado {{ $s->ultima_actualizacion_en?->format('d/m/Y H:i') }}</span></dd>
                    @endif
                    @if($s->motivo_cierre)
                        <dt class="fw-normal text-body-secondary">Motivo</dt>
                        <dd>{{ $s->motivo_cierre }}</dd>
                    @endif
                </dl>

                @if($p['editar'] && $s->estado === \Modules\Prestaciones\Enums\EstadoSolicitudPrestacion::Borrador)
                    <label for="sugerida" class="form-label fw-semibold mb-1 mt-2">Sugerir a alguien de tramitación</label>
                    <div class="d-flex gap-2">
                        <select id="sugerida" wire:model="sugeridaId" class="form-select form-select-sm">
                            <option value="">Sin sugerencia</option>
                            @foreach($this->tramitadores as $t)
                                <option value="{{ $t->id }}">{{ $t->nombreVisible() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="sugerir" class="btn btn-outline-secondary btn-sm">Guardar</button>
                    </div>
                @endif
            </div>
        </section>

        <section class="card" aria-labelledby="titulo-acciones">
            <div class="card-body d-flex flex-column gap-2">
                <h2 id="titulo-acciones" class="h6 fw-semibold mb-1">Acciones</h2>
                @if($p['aBandeja'])
                    <button type="button" wire:click="pasarABandeja" class="btn btn-primary btn-sm">Pasar a la bandeja de tramitación</button>
                @endif
                @if($p['tomar'])
                    <button type="button" wire:click="tomar" class="btn btn-primary btn-sm">Tomar la solicitud</button>
                @endif
                @if($p['responsable'])
                    <button type="button" wire:click="abrirPanel('enviar')" class="btn btn-primary btn-sm">Enviar</button>
                    <button type="button" wire:click="abrirPanel('soltar')" class="btn btn-outline-secondary btn-sm">Soltar</button>
                    <button type="button" wire:click="abrirPanel('devolver')" class="btn btn-outline-secondary btn-sm">Devolver al profesional</button>
                @endif
                @if($p['consultar'])
                    <button type="button" wire:click="consultarAhora" class="btn btn-outline-primary btn-sm">Consultar ahora</button>
                @endif
                @if($p['manual'])
                    <button type="button" wire:click="abrirPanel('estado')" class="btn btn-outline-secondary btn-sm">Registrar estado</button>
                    <button type="button" wire:click="abrirPanel('incidencia')" class="btn btn-outline-secondary btn-sm">Registrar incidencia</button>
                    <button type="button" wire:click="abrirPanel('resolucion')" class="btn btn-outline-secondary btn-sm">Registrar resolución</button>
                @endif
                @if($p['atender'] && $s->incidenciasAbiertas()->isNotEmpty())
                    <button type="button" wire:click="abrirPanel('atender')" class="btn btn-outline-warning btn-sm">Atender incidencia</button>
                @endif
                @if($p['anular'])
                    <button type="button" wire:click="abrirPanel('anular')" class="btn btn-outline-danger btn-sm">Anular</button>
                @endif

                @if($panel)
                    <div class="border-top pt-2 mt-1 small">
                        @if(in_array($panel, ['soltar', 'devolver', 'anular'], true))
                            <label for="panel-nota" class="form-label fw-semibold mb-1">{{ $panel === 'soltar' ? 'Nota (opcional)' : 'Motivo' }}</label>
                            <textarea id="panel-nota" wire:model="nota" rows="2" class="form-control form-control-sm mb-2"></textarea>
                        @elseif($panel === 'enviar')
                            @if($p['envioManual'])
                                <label for="panel-expediente" class="form-label fw-semibold mb-1">Número de expediente</label>
                                <input id="panel-expediente" type="text" wire:model="numeroExpediente" class="form-control form-control-sm mb-2">
                                <label for="panel-fecha" class="form-label fw-semibold mb-1">Fecha de presentación</label>
                                <input id="panel-fecha" type="date" wire:model="fechaPresentacion" class="form-control form-control-sm mb-2">
                            @endif
                            <label for="panel-justificacion" class="form-label fw-semibold mb-1">Justificación si faltan documentos obligatorios</label>
                            <textarea id="panel-justificacion" wire:model="justificacion" rows="2" class="form-control form-control-sm mb-2"></textarea>
                        @elseif($panel === 'estado')
                            <label for="m-texto" class="form-label fw-semibold mb-1">Estado (como aparece en la otra aplicación)</label>
                            <input id="m-texto" type="text" wire:model="manual.texto" class="form-control form-control-sm mb-2">
                            <label for="m-normalizado" class="form-label fw-semibold mb-1">Equivale a</label>
                            <select id="m-normalizado" wire:model="manual.normalizado" class="form-select form-select-sm mb-2">
                                @foreach(\App\Models\CatalogoSistema::opcionesParaSelect('expediente.estado') as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <label for="m-fecha" class="form-label fw-semibold mb-1">Fecha</label>
                            <input id="m-fecha" type="date" wire:model="manual.fecha" class="form-control form-control-sm mb-2">
                        @elseif($panel === 'incidencia')
                            <label for="m-tipo" class="form-label fw-semibold mb-1">Tipo</label>
                            <select id="m-tipo" wire:model="manual.tipo" class="form-select form-select-sm mb-2">
                                @foreach(\App\Models\CatalogoSistema::opcionesParaSelect('expediente.incidencia') as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <label for="m-desc" class="form-label fw-semibold mb-1">Descripción</label>
                            <textarea id="m-desc" wire:model="manual.descripcion" rows="2" class="form-control form-control-sm mb-2"></textarea>
                            <label for="m-fecha-i" class="form-label fw-semibold mb-1">Fecha</label>
                            <input id="m-fecha-i" type="date" wire:model="manual.fecha" class="form-control form-control-sm mb-2">
                            <label for="m-plazo" class="form-label fw-semibold mb-1">Plazo (opcional)</label>
                            <input id="m-plazo" type="date" wire:model="manual.plazo" class="form-control form-control-sm mb-2">
                        @elseif($panel === 'resolucion')
                            <label for="m-fecha-r" class="form-label fw-semibold mb-1">Fecha</label>
                            <input id="m-fecha-r" type="date" wire:model="manual.fecha" class="form-control form-control-sm mb-2">
                            <label for="m-sentido" class="form-label fw-semibold mb-1">Sentido</label>
                            <select id="m-sentido" wire:model="manual.sentido" class="form-select form-select-sm mb-2">
                                @foreach(\Modules\Prestaciones\Enums\SentidoResolucion::cases() as $sentido)
                                    <option value="{{ $sentido->value }}">{{ $sentido->label() }}</option>
                                @endforeach
                            </select>
                            @foreach($lineas as $i => $linea)
                                <div class="border rounded p-2 mb-2" wire:key="linea-{{ $i }}">
                                    <select wire:model="lineas.{{ $i }}.prestacion_id" class="form-select form-select-sm mb-1" aria-label="Prestación">
                                        @foreach($this->prestacionesCatalogo as $pc)
                                            <option value="{{ $pc->id }}">{{ $pc->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="linea-concedida-{{ $i }}" wire:model.live="lineas.{{ $i }}.concedida">
                                        <label class="form-check-label" for="linea-concedida-{{ $i }}">Concedida</label>
                                    </div>
                                    @if($lineas[$i]['concedida'])
                                        <input type="text" wire:model="lineas.{{ $i }}.intensidad" class="form-control form-control-sm mb-1" placeholder="Intensidad (p. ej. 20 h/mes)" aria-label="Intensidad">
                                        <input type="text" wire:model="lineas.{{ $i }}.importe" class="form-control form-control-sm" placeholder="Importe" aria-label="Importe">
                                    @else
                                        <input type="text" wire:model="lineas.{{ $i }}.motivo" class="form-control form-control-sm" placeholder="Motivo de la denegación" aria-label="Motivo de la denegación">
                                    @endif
                                </div>
                            @endforeach
                            <button type="button" wire:click="anadirLinea" class="btn btn-link btn-sm p-0 mb-2">Añadir línea</button>
                        @elseif($panel === 'atender')
                            <label for="m-incidencia" class="form-label fw-semibold mb-1">Incidencia</label>
                            <select id="m-incidencia" wire:model="incidenciaId" class="form-select form-select-sm mb-2">
                                <option value="">Elige…</option>
                                @foreach($s->incidenciasAbiertas() as $inc)
                                    <option value="{{ $inc->id }}">{{ $inc->detalle['descripcion'] ?? 'Incidencia' }} ({{ $inc->detalle['fecha'] ?? '' }})</option>
                                @endforeach
                            </select>
                            <p class="fw-semibold mb-1">Documentos que se aportan</p>
                            @foreach($this->documentosCarpeta->concat($this->documentosVinculados) as $doc)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="aporta-{{ $doc->id }}" value="{{ $doc->id }}" wire:model="documentosIncidencia">
                                    <label class="form-check-label" for="aporta-{{ $doc->id }}">{{ $doc->tipo?->nombre }} · {{ $doc->titulo }}</label>
                                </div>
                            @endforeach
                        @endif
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" wire:click="confirmar" class="btn btn-primary btn-sm">Confirmar</button>
                            <button type="button" wire:click="$set('panel', null)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
</div>
