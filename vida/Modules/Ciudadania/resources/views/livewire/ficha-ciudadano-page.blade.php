{{-- Ficha del ciudadano — Capa 1 --}}
{{-- Pivota sobre Ciudadano, no sobre HistoriaSocial --}}
<div class="citizen-file">
@php
    $ciudadano      = $this->ciudadano;
    $historiaSocial = $this->historiaSocial;
    $documentos     = $this->documentos;
    $prestaciones   = $this->prestaciones;
    $actividadRec   = $this->actividadReciente;
    $puedeEditar    = $this->puedeEditar;
    $puedeVerHS     = $this->puedeVerHistoria;
    $docActivo      = $documentos->first(fn($d) => $d->fecha_fin === null);
    $edad           = $fechaNacimiento ? \Carbon\Carbon::parse($fechaNacimiento)->age : null;

    $nivelBadge = match($ciudadano->nivel_identificacion ?? 'no_identificado') {
        'identificado'    => ['label' => 'Identificado',    'bg' => 'var(--color-success,#22c55e)', 'fg' => '#fff'],
        'probable'        => ['label' => 'Probable',        'bg' => 'var(--color-warning,#f59e0b)', 'fg' => '#fff'],
        default           => ['label' => 'No identificado', 'bg' => 'var(--color-danger,#ef4444)',  'fg' => '#fff'],
    };

@endphp

{{-- ===== CABECERA ===== --}}
<div class="citizen-file__header">
    <div>
        <div class="citizen-file__title-row">
            <h1 class="citizen-file__title">
                {{ $ciudadano->nombre_completo ?: '—' }}
            </h1>
            <span class="citizen-file__badge" style="--citizen-badge-bg: {{ $nivelBadge['bg'] }}; --citizen-badge-fg: {{ $nivelBadge['fg'] }};">{{ $nivelBadge['label'] }}</span>
        </div>
        <div class="citizen-file__meta">
            @if($docActivo)
                <span>
                    {{ strtoupper($docActivo->tipo) }}: {{ $docActivo->valor }}
                </span>
            @else
                <span class="citizen-file__meta-item citizen-file__meta-item--muted">Sin documento activo</span>
            @endif
            @if($edad !== null)
                <span>{{ $edad }} años</span>
            @endif
        </div>
    </div>

    <div class="citizen-file__actions">

        {{-- Botones de atención e historia social --}}
        @if($this->puedeCrearAtencion)
        <button wire:click="abrirModalAtencion" type="button" class="btn btn-outline-secondary btn-sm citizen-file__header-action">
            <i data-lucide="message-square-plus" class="icon-14" aria-hidden="true"></i>
            Nueva atención
        </button>
        @endif

        @if($this->puedeAbrirHistoria)
        <button
            wire:click="abrirHistoriaSocial"
            wire:confirm="¿Abrir historia social para este ciudadano? Esta acción asignará la historia a tu UO."
            type="button"
            class="btn btn-primary btn-sm citizen-file__header-action"
        >
            <i data-lucide="folder-plus" class="icon-14" aria-hidden="true"></i>
            Abrir historia social
        </button>
        @elseif($historiaSocial && $puedeVerHS)
        <a
            wire:navigate
            href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}"
            class="btn btn-primary btn-sm citizen-file__header-action"
        >
            <i data-lucide="folder-open" class="icon-14" aria-hidden="true"></i>
            Ver historia social
        </a>
        @endif

        {{-- Botones de edición de datos --}}
        @if($modoEdicion)
            <button wire:click="guardar" type="button" class="btn btn-primary btn-sm citizen-file__header-action">
                Guardar cambios
            </button>
            <button wire:click="cancelarEdicion" type="button" class="btn btn-outline-secondary btn-sm citizen-file__header-action">
                Cancelar
            </button>
        @elseif($puedeEditar)
            <button wire:click="activarEdicion" type="button" class="btn btn-outline-secondary btn-sm citizen-file__header-action">
                <i data-lucide="pencil" class="icon-14" aria-hidden="true"></i>
                Editar datos
            </button>
        @endif
    </div>
</div>

{{-- ===== VALIDACIÓN ===== --}}
@if($errors->any())
    <div class="citizen-file__alert">
        <ul class="citizen-file__alert-list">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ===== CONTENIDO DOS COLUMNAS ===== --}}
<div class="container-fluid citizen-file__content">
    <div class="row g-3">

        {{-- ===================== COLUMNA PRINCIPAL ===================== --}}
        <div class="col-lg-8">

            {{-- ——— Identificación y contacto ——— --}}
            <div class="citizen-file__card">
                <h2 class="citizen-file__section-title">
                    <i data-lucide="user" class="icon-16" aria-hidden="true"></i>
                    Identificación y contacto
                </h2>

                <div class="row g-3">
                    {{-- Nombre --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Nombre</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="nombre"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $nombre ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Apellido 1 --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Apellido 1</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="apellido1"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $apellido1 ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Apellido 2 --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Apellido 2</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="apellido2"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $apellido2 ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Fecha nacimiento --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Fecha de nacimiento</label>
                        @if($modoEdicion)
                            <input type="date" wire:model="fechaNacimiento"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">
                                {{ $fechaNacimiento ? \Carbon\Carbon::parse($fechaNacimiento)->format('d/m/Y') : '—' }}
                            </span>
                        @endif
                    </div>
                    {{-- Sexo --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Sexo</label>
                        @if($modoEdicion)
                            <select wire:model="sexo"
                                class="form-select form-select-sm ficha-input citizen-file__input">
                                <option value="">— Seleccionar —</option>
                                <option value="H">Hombre</option>
                                <option value="M">Mujer</option>
                                <option value="NB">No binario</option>
                            </select>
                        @else
                            <span class="citizen-file__field-value">
                                {{ match($sexo) { 'H' => 'Hombre', 'M' => 'Mujer', 'NB' => 'No binario', default => ($sexo ?: '—') } }}
                            </span>
                        @endif
                    </div>
                    {{-- Alias --}}
                    <div class="col-sm-4">
                        <label class="form-label ficha-label citizen-file__field-label">Alias / apodo</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="alias"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $alias ?: '—' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Separador contacto --}}
                <hr class="citizen-file__divider">

                <div class="row g-3">
                    {{-- Domicilio --}}
                    <div class="col-12">
                        <label class="form-label ficha-label citizen-file__field-label">Domicilio</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="direccionTexto"
                                placeholder="Texto libre — se normaliza al guardar"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $direccionTexto ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Teléfono --}}
                    <div class="col-sm-6">
                        <label class="form-label ficha-label citizen-file__field-label">Teléfono</label>
                        @if($modoEdicion)
                            <input type="tel" wire:model="telefono"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $telefono ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Email --}}
                    <div class="col-sm-6">
                        <label class="form-label ficha-label citizen-file__field-label">Email</label>
                        @if($modoEdicion)
                            <input type="email" wire:model="email"
                                class="form-control form-control-sm ficha-input citizen-file__input">
                        @else
                            <span class="citizen-file__field-value">{{ $email ?: '—' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Primera demanda (inmutable) --}}
                @if($ciudadano->primera_demanda)
                    <div class="citizen-file__note">
                        <div class="citizen-file__note-label">Primera demanda registrada en el alta</div>
                        <blockquote class="citizen-file__note-copy">
                            "{{ $ciudadano->primera_demanda }}"
                        </blockquote>
                    </div>
                @endif
            </div>

            {{-- ——— Documentos de identidad ——— --}}
            <div class="citizen-file__card">
                <div class="citizen-file__section-head">
                    <h2 class="citizen-file__section-title citizen-file__section-title--tight">
                        <i data-lucide="id-card" class="icon-16" aria-hidden="true"></i>
                        Documentos de identidad
                    </h2>
                    @if($puedeEditar)
                        <button wire:click="abrirModalDocumento" type="button" class="btn btn-outline-secondary btn-sm">
                            <i data-lucide="plus" class="icon-13" aria-hidden="true"></i>
                            Añadir documento
                        </button>
                    @endif
                </div>

                @if($documentos->isEmpty())
                    <p class="citizen-file__empty">Sin documentos registrados.</p>
                @else
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0 citizen-file__table">
                        <thead>
                            <tr class="citizen-file__table-head">
                                <th class="citizen-file__th">Tipo</th>
                                <th class="citizen-file__th">Valor</th>
                                <th class="citizen-file__th">Inicio</th>
                                <th class="citizen-file__th">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentos as $doc)
                            @php
                                $esActivo  = $doc->fecha_fin === null;
                                $estadoDoc  = $esActivo ? 'Activo' : 'Sustituido';
                                $estadoBg   = $esActivo ? '#dcfce7' : '#f3f4f6';
                                $estadoFg   = $esActivo ? '#166534' : '#374151';
                            @endphp
                            <tr class="citizen-file__table-row {{ $esActivo ? '' : 'citizen-file__table-row--muted' }}">
                                <td class="citizen-file__td">{{ strtoupper($doc->tipo) }}</td>
                                <td class="citizen-file__td citizen-file__td--mono">{{ $doc->valor }}</td>
                                <td class="citizen-file__td">{{ $doc->fecha_inicio?->format('d/m/Y') }}</td>
                                <td class="citizen-file__td">
                                    <span class="citizen-file__status-pill" style="--citizen-pill-bg: {{ $estadoBg }}; --citizen-pill-fg: {{ $estadoFg }};">
                                        {{ $estadoDoc }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                    <p class="citizen-file__helper">
                        Los documentos anteriores no se eliminan — permiten localizar al ciudadano aunque haya cambiado de documento.
                    </p>
                @endif
            </div>

            {{-- ——— Relaciones ——— --}}
            @php
                $relacionesActivas  = $this->relacionesActivas;
                $relacionesHist     = $this->relacionesHistoricas->filter(fn($r) => $r->fecha_fin !== null);
                $puedeEditarRel     = $this->puedeEditarRelaciones;
            @endphp
            <div class="citizen-file__card">
                <div class="citizen-file__section-head">
                    <h2 class="citizen-file__section-title citizen-file__section-title--tight">
                        <i data-lucide="users" class="icon-16" aria-hidden="true"></i>
                        Relaciones
                    </h2>
                    @if($puedeEditarRel)
                        <button wire:click="abrirModalNuevaRelacion" type="button" class="btn btn-outline-secondary btn-sm">
                            <i data-lucide="plus" class="icon-13" aria-hidden="true"></i>
                            Añadir relación
                        </button>
                    @endif
                </div>

                @if($relacionMensaje)
                    <div class="citizen-file__flash">
                        {{ $relacionMensaje }}
                    </div>
                @endif

                @if($relacionesActivas->isEmpty())
                    <p class="citizen-file__empty">
                        Sin relaciones registradas.
                    </p>
                @else
                    <div class="list-group list-group-flush citizen-file__list">
                        @foreach($relacionesActivas as $rel)
                        @php
                            $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                            $nombreRel    = $rel->ciudadanoRelacionado?->nombre_completo ?? '—';
                            $fichaUrl     = $rel->ciudadano_relacionado_id
                                ? route('ciudadania.ciudadano.ficha', $rel->ciudadano_relacionado_id)
                                : null;
                        @endphp
                        <div class="list-group-item citizen-file__list-row {{ $puedeEditarRel ? 'citizen-file__list-row--clickable' : '' }}" @if($puedeEditarRel) wire:click="abrirModalEditarRelacion({{ $rel->id }})" @endif>
                            <span class="citizen-file__list-chip">
                                {{ $etiquetaTipo }}
                            </span>
                            @if($fichaUrl)
                                <a wire:navigate href="{{ $fichaUrl }}"
                                   class="citizen-file__list-link"
                                   wire:click.stop>
                                    {{ $nombreRel }}
                                </a>
                            @else
                                <span class="citizen-file__list-name">{{ $nombreRel }}</span>
                            @endif
                            @if($puedeEditarRel)
                                <i data-lucide="chevron-right" class="icon-14 citizen-file__list-chevron" aria-hidden="true"></i>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif

                @if($relacionesHist->isNotEmpty())
                    <div class="citizen-file__history">
                        <button wire:click="toggleHistorialRelaciones" type="button"
                            class="btn btn-link btn-sm text-decoration-none px-0 citizen-file__history-toggle">
                            <i data-lucide="{{ $mostrarHistorialRelaciones ? 'chevron-up' : 'chevron-down' }}" class="icon-13" aria-hidden="true"></i>
                            {{ $mostrarHistorialRelaciones ? 'Ocultar historial' : "Ver historial ({$relacionesHist->count()})" }}
                        </button>
                        @if($mostrarHistorialRelaciones)
                            <div class="list-group list-group-flush citizen-file__history-list">
                                @foreach($relacionesHist as $rel)
                                @php
                                    $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                                @endphp
                                <div class="list-group-item citizen-file__history-row">
                                    <span class="citizen-file__history-chip">
                                        {{ $etiquetaTipo }}
                                    </span>
                                    <span class="citizen-file__history-name">{{ $rel->ciudadanoRelacionado?->nombre_completo ?? '—' }}</span>
                                    <span class="citizen-file__history-date">
                                        hasta {{ $rel->fecha_fin?->format('d/m/Y') }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ——— Unidad de convivencia (solo lectura) ——— --}}
            @php $ucMiembros = $this->ucMiembros; @endphp
            @if($ucMiembros->isNotEmpty())
            <div class="citizen-file__card citizen-file__card--flush">
                <h2 class="citizen-file__section-title citizen-file__section-title--compact">
                    <i data-lucide="home" class="icon-16" aria-hidden="true"></i>
                    Unidad de convivencia
                </h2>
                <div class="list-group list-group-flush citizen-file__list">
                    @foreach($ucMiembros as $miembro)
                    @php
                        $nombreMiembro = $miembro->ciudadano?->nombre_completo ?? '—';
                        $fichaUrl      = $miembro->ciudadano_id
                            ? route('ciudadania.ciudadano.ficha', $miembro->ciudadano_id)
                            : null;
                    @endphp
                    <div class="list-group-item citizen-file__list-row citizen-file__list-row--plain">
                        @if($miembro->tipo_relacion_etiqueta)
                            <span class="citizen-file__list-chip">
                                {{ $miembro->tipo_relacion_etiqueta }}
                            </span>
                        @endif
                        @if($fichaUrl)
                            <a wire:navigate href="{{ $fichaUrl }}"
                               class="citizen-file__list-link">
                                {{ $nombreMiembro }}
                            </a>
                        @else
                            <span class="citizen-file__list-name">{{ $nombreMiembro }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>{{-- /col-lg-8 --}}

        {{-- ===================== COLUMNA LATERAL ===================== --}}
        <div class="col-lg-4">

            {{-- ——— Otras prestaciones ——— --}}
            @if($prestaciones->isNotEmpty())
                <div class="citizen-file__card">
                    <h2 class="citizen-file__section-title citizen-file__section-title--compact">
                        <i data-lucide="layers" class="icon-16" aria-hidden="true"></i>
                        Otras prestaciones
                    </h2>
                    <div class="list-group list-group-flush citizen-file__stack">
                        @foreach($prestaciones as $pres)
                        @php
                            [$bg, $fg] = match($pres->estado) {
                                'activo'     => ['#dcfce7', '#166534'],
                                'en_tramite' => ['#fef3c7', '#92400e'],
                                'finalizado' => ['#f3f4f6', '#374151'],
                                default      => ['#fee2e2', '#991b1b'],
                            };
                            $estadoLabel = match($pres->estado) {
                                'activo'     => 'Activo',
                                'en_tramite' => 'En trámite',
                                'finalizado' => 'Finalizado',
                                'denegado'   => 'Denegado',
                                'baja'       => 'Baja',
                                default      => $pres->estado,
                            };
                        @endphp
                        <div class="list-group-item citizen-file__stack-row">
                            <div>
                                <div class="citizen-file__stack-title">{{ $pres->descripcion }}</div>
                                <div class="citizen-file__stack-meta">{{ $pres->fecha_inicio?->format('d/m/Y') }}</div>
                            </div>
                            <span class="citizen-file__status-pill" style="--citizen-pill-bg: {{ $bg }}; --citizen-pill-fg: {{ $fg }};">
                                {{ $estadoLabel }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ——— Historial de atenciones ——— --}}
            @if($this->historialAtenciones->isNotEmpty() || $this->puedeCrearAtencion)
            <div class="ficha-section citizen-file__timeline" id="ficha-atencion-historial">
                <div class="ficha-section-header">
                    <div class="ficha-section-title">
                        <i data-lucide="history" class="icon-14" aria-hidden="true"></i>
                        Historial de atenciones
                        <span class="ficha-count">{{ $this->historialAtenciones->count() }}</span>
                    </div>
                </div>

                @forelse($this->historialAtenciones as $registro)
                <div class="ficha-atencion-row" wire:key="ra-{{ $registro->id }}"
                     x-data="{ expandido: false }">
                    <div class="ficha-atencion-meta">
                        <span class="ficha-atencion-fecha">{{ $registro->fecha->format('d/m/Y') }}</span>
                        <span class="ficha-atencion-tipo ficha-atencion-tipo--{{ $registro->tipo }}">
                            {{ match($registro->tipo) {
                                'informacion' => 'Información',
                                'actividad'   => 'Actividad',
                                'contacto'    => 'Contacto',
                                default       => $registro->tipo,
                            } }}
                        </span>
                        @if($registro->profesional)
                        <span class="ficha-atencion-prof">{{ $registro->profesional->name }}</span>
                        @endif
                        @if($registro->prestacion)
                        <span class="ficha-atencion-prest">{{ $registro->prestacion->nombre }}</span>
                        @endif
                    </div>
                    <div class="ficha-atencion-resumen">
                        {{ $registro->resumenHistorial() }}
                    </div>
                    @if($registro->demanda || $registro->respuesta)
                    <button
                        class="btn btn-link btn-sm p-0 align-self-start d-inline-flex align-items-center gap-1"
                        @click="expandido = !expandido"
                        :aria-expanded="expandido"
                        type="button"
                    >
                        <span x-text="expandido ? 'Ocultar' : 'Ver detalle'"></span>
                        <i data-lucide="chevron-down" class="icon-12"
                           :style="expandido ? 'transform:rotate(180deg)' : ''"
                           aria-hidden="true"></i>
                    </button>
                    <div class="ficha-atencion-detalle" x-show="expandido" x-cloak>
                        @if($registro->demanda)
                        <div class="ficha-atencion-campo">
                            <div class="ficha-atencion-campo-label">Demanda</div>
                            <div class="ficha-atencion-campo-valor">{{ $registro->demanda }}</div>
                        </div>
                        @endif
                        @if($registro->respuesta)
                        <div class="ficha-atencion-campo">
                            <div class="ficha-atencion-campo-label">Respuesta</div>
                            <div class="ficha-atencion-campo-valor">{{ $registro->respuesta }}</div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
                @empty
                <div class="ficha-atencion-vacia">Sin atenciones registradas.</div>
                @endforelse
            </div>
            @endif


        </div>{{-- /col-lg-4 --}}

    </div>{{-- /row --}}
</div>

{{-- ===== MODAL RELACIÓN ===== --}}
@if($modalRelacionAbierto)
    <div class="citizen-file__modal-overlay"
         wire:click.self="cerrarModalRelacion">
        <div class="citizen-file__modal-dialog">

            <div class="citizen-file__modal-header">
                <h3 class="citizen-file__modal-title">
                    {{ $relacionId ? 'Editar relación' : 'Nueva relación' }}
                </h3>
                <button wire:click="cerrarModalRelacion" type="button" class="btn-close citizen-file__modal-close" aria-label="Cerrar"></button>
            </div>

            {{-- Tipo de relación (solo en creación) --}}
            @if(! $relacionId)
            <div class="citizen-file__modal-field">
                <label class="citizen-file__modal-label">
                    Tipo de relación <span class="citizen-file__required">*</span>
                </label>
                <select wire:model="relacionTipo"
                    class="form-select form-select-sm ficha-input citizen-file__input">
                                <option value="">— Seleccionar —</option>
                    @foreach($this->tiposRelacion as $slug => $etiqueta)
                        <option value="{{ $slug }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('relacionTipo')
                    <span class="ficha-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Buscador ciudadano (solo en creación) --}}
            <div class="citizen-file__modal-field citizen-file__modal-field--search">
                <label class="citizen-file__modal-label">
                    Ciudadano <span class="citizen-file__required">*</span>
                </label>
                @if($this->ciudadanoSeleccionadoRelacion)
                    <div class="citizen-file__selected d-flex align-items-center gap-2">
                        <span class="citizen-file__selected-name">{{ $this->ciudadanoSeleccionadoRelacion->nombre_completo }}</span>
                        <button type="button" wire:click="$set('relacionCiudadanoSeleccionado', null)"
                            class="btn btn-sm btn-outline-secondary p-1 citizen-file__clear-btn">
                            <i data-lucide="x" class="icon-14" aria-hidden="true"></i>
                        </button>
                    </div>
                @else
                    <input type="text" wire:model.live="relacionBusqueda"
                        placeholder="Escribir nombre (mín. 2 caracteres)…"
                        class="form-control form-control-sm ficha-input citizen-file__input">
                    @if($this->relacionResultadosBusqueda->isNotEmpty())
                        <div class="citizen-file__search-results list-group">
                            @foreach($this->relacionResultadosBusqueda as $sug)
                                <button type="button" wire:click="seleccionarCiudadanoRelacion({{ $sug->id }})"
                                    class="citizen-file__search-result list-group-item list-group-item-action">
                                    {{ $sug->nombre_completo }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                @error('relacionCiudadanoSeleccionado')
                    <span class="ficha-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Fecha inicio (solo en creación) --}}
            <div class="citizen-file__modal-field">
                <label class="citizen-file__modal-label">
                    Fecha de inicio <span class="citizen-file__required">*</span>
                </label>
                <input type="date" wire:model="relacionFechaInicio"
                    class="form-control form-control-sm ficha-input citizen-file__input">
                @error('relacionFechaInicio')
                    <span class="ficha-error">{{ $message }}</span>
                @enderror
            </div>
            @endif

            {{-- Observaciones (creación y edición) --}}
            <div class="citizen-file__modal-field citizen-file__modal-field--last">
                <label class="citizen-file__modal-label">Observaciones</label>
                <textarea wire:model="relacionObservaciones" rows="3" placeholder="Opcional…"
                    class="form-control form-control-sm ficha-textarea citizen-file__input citizen-file__textarea"></textarea>
                @error('relacionObservaciones')
                    <span class="ficha-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="citizen-file__modal-footer citizen-file__modal-footer--split">
                <div>
                    @if($relacionId)
                        <button wire:click="cerrarRelacion({{ $relacionId }})" type="button"
                            wire:confirm="¿Confirmar el cierre de esta relación? Se establecerá fecha de fin hoy."
                            class="btn btn-sm btn-outline-danger citizen-file__danger-btn">
                            Cerrar relación
                        </button>
                    @endif
                </div>
                <div class="citizen-file__modal-actions">
                    <button wire:click="cerrarModalRelacion" type="button"
                        class="btn btn-outline-secondary btn-sm">
                        Cancelar
                    </button>
                    <button wire:click="guardarRelacion" type="button"
                        class="btn btn-primary btn-sm">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ===== MODAL NUEVO DOCUMENTO ===== --}}
@if($modalDocumento)
    <div class="citizen-file__modal-overlay citizen-file__modal-overlay--front" wire:click.self="cerrarModalDocumento">
        <div class="citizen-file__modal-dialog citizen-file__modal-dialog--sm">
            <div class="citizen-file__modal-header">
                <h3 class="citizen-file__modal-title">Añadir documento de identidad</h3>
                <button wire:click="cerrarModalDocumento" type="button" class="btn-close citizen-file__modal-close" aria-label="Cerrar"></button>
            </div>
            <p class="citizen-file__modal-copy">
                El documento actual recibirá fecha de fin. El historial se conserva íntegro.
            </p>

            <div class="citizen-file__modal-field">
                <label class="citizen-file__modal-label">Tipo de documento</label>
                <select wire:model="nuevoTipoDocumento"
                    class="form-select form-select-sm ficha-input citizen-file__input">
                    <option value="nif">DNI / NIF</option>
                    <option value="nie">NIE</option>
                    <option value="pasaporte">Pasaporte</option>
                </select>
            </div>
            <div class="citizen-file__modal-field citizen-file__modal-field--last">
                <label class="citizen-file__modal-label">Número de documento</label>
                <input type="text" wire:model="nuevoValorDocumento" placeholder="Ej.: 12345678A"
                    class="form-control form-control-sm ficha-input citizen-file__input">
                @error('nuevoValorDocumento')
                    <span class="ficha-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="citizen-file__modal-actions citizen-file__modal-actions--end">
                <button wire:click="cerrarModalDocumento" type="button"
                    class="btn btn-outline-secondary btn-sm">
                    Cancelar
                </button>
                <button wire:click="guardarDocumento" type="button"
                    class="btn btn-primary btn-sm">
                    Guardar documento
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ===== MODAL NUEVA ATENCIÓN ===== --}}
@if($this->modalAtencionAbierto)
<div
    class="modal fade show d-block"
    wire:click.self="cerrarModalAtencion"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalAtencion()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-atencion-titulo"
    tabindex="-1"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h2 id="modal-atencion-titulo" class="modal-title fs-6">Nueva atención</h2>
                <button wire:click="cerrarModalAtencion" aria-label="Cerrar" class="btn-close" type="button"></button>
            </div>

            <div class="modal-body d-flex flex-column gap-3">

                <div class="ficha-field">
                    <label class="form-label ficha-label" for="at-fecha">Fecha</label>
                    <input
                        type="date"
                        id="at-fecha"
                        wire:model="atencionFecha"
                        class="form-control form-control-sm ficha-input"
                        max="{{ now()->toDateString() }}"
                    >
                    @error('atencionFecha') <span class="ficha-error">{{ $message }}</span> @enderror
                </div>

                @if(! auth()->user()->hasRole('consulta_basica'))
                <div class="ficha-field">
                    <label class="form-label ficha-label">Tipo de atención</label>
                    <div class="ficha-radio-group">
                        <label class="ficha-radio">
                            <input type="radio" wire:model="atencionTipo" value="informacion">
                            Información / orientación
                        </label>
                        <label class="ficha-radio">
                            <input type="radio" wire:model="atencionTipo" value="contacto">
                            Contacto (llamada, email…)
                        </label>
                    </div>
                </div>
                @endif

                <div class="ficha-field">
                    <label class="form-label ficha-label" for="at-demanda">Demanda del ciudadano</label>
                    <textarea
                        id="at-demanda"
                        wire:model="atencionDemanda"
                        class="form-control form-control-sm ficha-textarea"
                        rows="3"
                        placeholder="Qué solicita o comunica el ciudadano…"
                    ></textarea>
                    @error('atencionDemanda') <span class="ficha-error">{{ $message }}</span> @enderror
                </div>

                <div class="ficha-field">
                    <label class="form-label ficha-label" for="at-respuesta">Respuesta / actuación</label>
                    <textarea
                        id="at-respuesta"
                        wire:model="atencionRespuesta"
                        class="form-control form-control-sm ficha-textarea"
                        rows="2"
                        placeholder="Qué se le informa, orienta o tramita…"
                    ></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button wire:click="cerrarModalAtencion" class="btn btn-outline-secondary btn-sm" type="button">Cancelar</button>
                <button wire:click="guardarAtencion" class="btn btn-primary btn-sm" type="button">
                    <i data-lucide="check" class="icon-13" aria-hidden="true"></i>
                    Guardar atención
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

</div>
