{{-- Ficha del ciudadano — Capa 1 --}}
{{-- Pivota sobre Ciudadano, no sobre HistoriaSocial --}}
<div class="op-page">
@php
    use Modules\Ciudadania\Support\Ui\Tonos;

    $ciudadano      = $this->ciudadano;
    $historiaSocial = $this->historiaSocial;
    $documentos     = $this->documentos;
    $prestaciones   = $this->prestaciones;
    $actividadRec   = $this->actividadReciente;
    $puedeEditar    = $this->puedeEditar;
    $puedeVerHS     = $this->puedeVerHistoria;
    $docActivo      = $documentos->first(fn($d) => $d->fecha_fin === null);
    $edad           = $fechaNacimiento ? \Carbon\Carbon::parse($fechaNacimiento)->age : null;

    $nivelEtiqueta = match($ciudadano->nivel_identificacion ?? 'no_identificado') {
        'identificado' => 'Identificado',
        'probable'     => 'Probable',
        default        => 'No identificado',
    };
@endphp

{{-- ===== CABECERA ===== --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 px-3 py-3 border-bottom bg-body">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h4 fw-bold mb-0">
                {{ $ciudadano->nombre_completo ?: '—' }}
            </h1>
            <span class="badge {{ Tonos::nivelIdentificacion($ciudadano->nivel_identificacion)->clasesFuerte() }}">{{ $nivelEtiqueta }}</span>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-body-secondary mt-1">
            @if($docActivo)
                <span class="font-monospace">{{ strtoupper($docActivo->tipo) }}: {{ $docActivo->valor }}</span>
            @else
                <span class="fst-italic">Sin documento activo</span>
            @endif
            @if($edad !== null)
                <span>{{ $edad }} años</span>
            @endif
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">

        {{-- Tras registrar una atención, quien da citas puede darla desde ella (cita generada) --}}
        @if($atencionMensaje !== '' && $ultimaAtencionId && $this->puedeDarCita)
        <a href="{{ route('agenda.citas.nueva', ['ciudadano' => $ciudadanoId, 'atencion' => $ultimaAtencionId]) }}" wire:navigate class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
            <x-heroicon-o-calendar-days class="icon-14" aria-hidden="true"/>
            Dar cita
        </a>
        @endif

        {{-- Botones de atención e historia social --}}
        @if($this->puedeCrearAtencion)
        <button wire:click="abrirModalAtencion" type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <x-heroicon-o-chat-bubble-left-ellipsis class="icon-14" aria-hidden="true"/>
            Nueva atención
        </button>
        @endif

        @if($this->puedeAbrirHistoria)
        <button
            wire:click="abrirHistoriaSocial"
            wire:confirm="¿Abrir historia social para este ciudadano? Esta acción asignará la historia a tu UO."
            type="button"
            class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"
        >
            <x-heroicon-o-folder-plus class="icon-14" aria-hidden="true"/>
            Abrir historia social
        </button>
        @elseif($historiaSocial && $puedeVerHS)
        <a
            wire:navigate
            href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}"
            class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"
        >
            <x-heroicon-o-folder-open class="icon-14" aria-hidden="true"/>
            Ir a HS
        </a>
        @elseif($historiaSocial)
        <span class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 disabled" aria-disabled="true">
            <x-heroicon-o-folder-open class="icon-14" aria-hidden="true"/>
            Ir a HS
        </span>
        @endif

        {{-- Botones de edición de datos --}}
        @if($modoEdicion)
            <button wire:click="guardar" type="button" class="btn btn-primary btn-sm">
                Guardar cambios
            </button>
            <button wire:click="cancelarEdicion" type="button" class="btn btn-outline-secondary btn-sm">
                Cancelar
            </button>
        @elseif($puedeEditar)
            <button wire:click="activarEdicion" type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                <x-heroicon-o-pencil class="icon-14" aria-hidden="true"/>
                Editar datos
            </button>
        @endif
    </div>
</div>

{{-- ===== AVISO TRAS ABRIR LA HISTORIA EN EL ALTA ===== --}}
@if(session('referencia-asignada'))
    <div class="alert alert-info small mx-3 mt-3 mb-0" role="status">{{ session('referencia-asignada') }}</div>
@endif

{{-- ===== VALIDACIÓN ===== --}}
@if($errors->any())
    <div class="alert alert-danger small mx-3 mt-3 mb-0">
        <ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ===== CONTENIDO DOS COLUMNAS ===== --}}
<div class="container-fluid py-3">
    <div class="row g-3">

        {{-- ===================== COLUMNA PRINCIPAL ===================== --}}
        <div class="col-lg-8">

            {{-- ——— Identificación y contacto ——— --}}
            <div class="card card-body mb-3">
                <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-3">
                    <x-heroicon-o-user class="icon-16" aria-hidden="true"/>
                    Identificación y contacto
                </h2>

                <div class="row g-3">
                    {{-- Nombre --}}
                    <div class="col-sm-4">
                        <label for="ficha-nombre" class="form-label small text-body-secondary mb-1">Nombre</label>
                        @if($modoEdicion)
                            <input id="ficha-nombre" type="text" wire:model="nombre" class="form-control form-control-sm">
                        @else
                            <div>{{ $nombre ?: '—' }}</div>
                        @endif
                    </div>
                    {{-- Apellido 1 --}}
                    <div class="col-sm-4">
                        <label for="ficha-apellido1" class="form-label small text-body-secondary mb-1">Apellido 1</label>
                        @if($modoEdicion)
                            <input id="ficha-apellido1" type="text" wire:model="apellido1" class="form-control form-control-sm">
                        @else
                            <div>{{ $apellido1 ?: '—' }}</div>
                        @endif
                    </div>
                    {{-- Apellido 2 --}}
                    <div class="col-sm-4">
                        <label for="ficha-apellido2" class="form-label small text-body-secondary mb-1">Apellido 2</label>
                        @if($modoEdicion)
                            <input id="ficha-apellido2" type="text" wire:model="apellido2" class="form-control form-control-sm">
                        @else
                            <div>{{ $apellido2 ?: '—' }}</div>
                        @endif
                    </div>
                    {{-- Fecha nacimiento --}}
                    <div class="col-sm-4">
                        <label for="ficha-fecha" class="form-label small text-body-secondary mb-1">Fecha de nacimiento</label>
                        @if($modoEdicion)
                            <input id="ficha-fecha" type="date" wire:model="fechaNacimiento" class="form-control form-control-sm">
                        @else
                            <div>{{ $fechaNacimiento ? \Carbon\Carbon::parse($fechaNacimiento)->format('d/m/Y') : '—' }}</div>
                        @endif
                    </div>
                    {{-- Sexo --}}
                    <div class="col-sm-4">
                        <label for="ficha-sexo" class="form-label small text-body-secondary mb-1">Sexo</label>
                        @if($modoEdicion)
                            <select id="ficha-sexo" wire:model="sexo" class="form-select form-select-sm">
                                <option value="">— Seleccionar —</option>
                                @foreach($this->opcionesSexo as $clave => $etiqueta)
                                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        @else
                            {{-- Un código fuera del catálogo se muestra tal cual para que se vea y se corrija --}}
                            <div>{{ $this->opcionesSexo[$sexo] ?? ($sexo ?: '—') }}</div>
                        @endif
                    </div>
                    {{-- Alias --}}
                    <div class="col-sm-4">
                        <label for="ficha-alias" class="form-label small text-body-secondary mb-1">Alias / apodo</label>
                        @if($modoEdicion)
                            <input id="ficha-alias" type="text" wire:model="alias" class="form-control form-control-sm">
                        @else
                            <div>{{ $alias ?: '—' }}</div>
                        @endif
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    {{-- Domicilio --}}
                    <div class="col-12">
                        <label for="ficha-direccion" class="form-label small text-body-secondary mb-1">Domicilio</label>
                        @if($modoEdicion)
                            <input id="ficha-direccion" type="text" wire:model="direccionTexto"
                                placeholder="Texto libre — se normaliza al guardar"
                                class="form-control form-control-sm">
                        @else
                            <div>{{ $direccionTexto ?: '—' }}</div>
                        @endif
                    </div>
                    {{-- Teléfono --}}
                    <div class="col-sm-6">
                        <label for="ficha-telefono" class="form-label small text-body-secondary mb-1">Teléfono</label>
                        @if($modoEdicion)
                            <input id="ficha-telefono" type="tel" wire:model="telefono" class="form-control form-control-sm">
                        @else
                            <div>{{ $telefono ?: '—' }}</div>
                        @endif
                    </div>
                    {{-- Email --}}
                    <div class="col-sm-6">
                        <label for="ficha-email" class="form-label small text-body-secondary mb-1">Email</label>
                        @if($modoEdicion)
                            <input id="ficha-email" type="email" wire:model="email" class="form-control form-control-sm">
                        @else
                            <div>{{ $email ?: '—' }}</div>
                        @endif
                    </div>
                </div>

                {{-- Primera demanda (inmutable) --}}
                @if($ciudadano->primera_demanda)
                    <figure class="bg-body-tertiary rounded p-3 mt-3 mb-0">
                        <figcaption class="small text-uppercase fw-semibold text-body-secondary mb-1">Primera demanda registrada en el alta</figcaption>
                        <blockquote class="fst-italic mb-0">
                            "{{ $ciudadano->primera_demanda }}"
                        </blockquote>
                    </figure>
                @endif
            </div>

            {{-- ——— Documentos de identidad ——— --}}
            <div class="card card-body mb-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-0">
                        <x-heroicon-o-identification class="icon-16" aria-hidden="true"/>
                        Documentos de identidad
                    </h2>
                    @if($puedeEditar)
                        <button wire:click="abrirModalDocumento" type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                            <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                            Añadir documento
                        </button>
                    @endif
                </div>

                @if($documentos->isEmpty())
                    <p class="small text-body-secondary mb-0">Sin documentos registrados.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="small text-body-secondary">
                                <tr>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Inicio</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documentos as $doc)
                                @php $esActivo = $doc->fecha_fin === null; @endphp
                                <tr @class(['text-body-tertiary' => ! $esActivo])>
                                    <td>{{ strtoupper($doc->tipo) }}</td>
                                    <td class="font-monospace">{{ $doc->valor }}</td>
                                    <td>{{ $doc->fecha_inicio?->format('d/m/Y') }}</td>
                                    <td>
                                        @if($esActivo)
                                            <span class="badge bg-success-subtle text-success-emphasis">Activo</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Sustituido</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0">
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
            <div class="card card-body mb-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-0">
                        <x-heroicon-o-users class="icon-16" aria-hidden="true"/>
                        Relaciones
                    </h2>
                    @if($puedeEditarRel)
                        <button wire:click="abrirModalNuevaRelacion" type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                            <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                            Añadir relación
                        </button>
                    @endif
                </div>

                @if($relacionMensaje)
                    <div class="alert alert-success py-2 small" role="status">
                        {{ $relacionMensaje }}
                    </div>
                @endif

                @if($relacionesActivas->isEmpty())
                    <p class="small text-body-secondary mb-0">
                        Sin relaciones registradas.
                    </p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($relacionesActivas as $rel)
                        @php
                            $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                            $nombreRel    = $rel->ciudadanoRelacionado?->nombre_completo ?? '—';
                            $fichaUrl     = $rel->ciudadano_relacionado_id
                                ? route('ciudadania.ciudadano.ficha', $rel->ciudadano_relacionado_id)
                                : null;
                        @endphp
                        <div @class(['list-group-item d-flex align-items-center gap-2 px-0', 'list-group-item-action' => $puedeEditarRel])
                             @if($puedeEditarRel) wire:click="abrirModalEditarRelacion({{ $rel->id }})" role="button" @endif>
                            <span class="badge bg-primary-subtle text-primary-emphasis">
                                {{ $etiquetaTipo }}
                            </span>
                            @if($fichaUrl)
                                <a wire:navigate href="{{ $fichaUrl }}" class="fw-semibold" wire:click.stop>
                                    {{ $nombreRel }}
                                </a>
                            @else
                                <span class="fw-semibold">{{ $nombreRel }}</span>
                            @endif
                            @if($puedeEditarRel)
                                <x-heroicon-o-chevron-right class="icon-14 ms-auto text-body-tertiary" aria-hidden="true"/>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif

                @if($relacionesHist->isNotEmpty())
                    <div class="mt-2">
                        <button wire:click="toggleHistorialRelaciones" type="button"
                            class="btn btn-link btn-sm text-decoration-none px-0 d-inline-flex align-items-center gap-1">
                            <x-dynamic-component :component="$mostrarHistorialRelaciones ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'" class="icon-14" aria-hidden="true"/>
                            {{ $mostrarHistorialRelaciones ? 'Ocultar historial' : "Ver historial ({$relacionesHist->count()})" }}
                        </button>
                        @if($mostrarHistorialRelaciones)
                            <div class="list-group list-group-flush">
                                @foreach($relacionesHist as $rel)
                                @php
                                    $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                                @endphp
                                <div class="list-group-item d-flex align-items-center gap-2 px-0 small text-body-secondary">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                        {{ $etiquetaTipo }}
                                    </span>
                                    <span>{{ $rel->ciudadanoRelacionado?->nombre_completo ?? '—' }}</span>
                                    <span class="ms-auto">
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
            <div class="card card-body mb-3">
                <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                    <x-heroicon-o-home class="icon-16" aria-hidden="true"/>
                    Unidad de convivencia
                </h2>
                <div class="list-group list-group-flush">
                    @foreach($ucMiembros as $miembro)
                    @php
                        $nombreMiembro = $miembro->ciudadano?->nombre_completo ?? '—';
                        $fichaUrl      = $miembro->ciudadano_id
                            ? route('ciudadania.ciudadano.ficha', $miembro->ciudadano_id)
                            : null;
                    @endphp
                    <div class="list-group-item d-flex align-items-center gap-2 px-0">
                        @if($miembro->tipo_relacion_etiqueta)
                            <span class="badge bg-primary-subtle text-primary-emphasis">
                                {{ $miembro->tipo_relacion_etiqueta }}
                            </span>
                        @endif
                        @if($fichaUrl)
                            <a wire:navigate href="{{ $fichaUrl }}" class="fw-semibold">
                                {{ $nombreMiembro }}
                            </a>
                        @else
                            <span class="fw-semibold">{{ $nombreMiembro }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ——— Documentos (módulo Documentos, custodia v2) ——— --}}
            <livewire:documentos.documentos-ciudadano :ciudadano-id="$ciudadanoId" :key="'documentos-'.$ciudadanoId" />

        </div>{{-- /col-lg-8 --}}

        {{-- ===================== COLUMNA LATERAL ===================== --}}
        <div class="col-lg-4">

            {{-- ——— Centro y profesional de referencia (docs/modulo-asignacion.md) ——— --}}
            @php
                $centrosAsig = $this->asignacionesCentro;
                $referencias = $this->asignacionesReferencia;
                $referenciaVigente = $referencias->first(fn ($a) => $a->fecha_fin === null);
                $historialAsig = $centrosAsig->whereNotNull('fecha_fin')->count() + $referencias->whereNotNull('fecha_fin')->count();
            @endphp
            <div class="card card-body mb-3" id="ficha-asignaciones">
                <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                    <x-heroicon-o-building-office class="icon-16" aria-hidden="true"/>
                    Centro y referencia
                </h2>
                <dl class="small mb-0">
                    @forelse($centrosAsig->whereNull('fecha_fin') as $asig)
                        <dt class="text-body-secondary fw-normal">{{ $this->tiposCentro[$asig->tipo_centro] ?? $asig->tipo_centro }}</dt>
                        <dd class="mb-2">
                            <span class="fw-semibold">{{ $asig->centro?->nombre }}</span>
                            <span class="d-block text-body-secondary">{{ $asig->modo->label() }} · desde el {{ $asig->fecha_inicio->format('d/m/Y') }}</span>
                        </dd>
                    @empty
                        <dt class="text-body-secondary fw-normal">Centro</dt>
                        <dd class="mb-2">Sin centro asignado</dd>
                    @endforelse

                    @if($historiaSocial)
                        <dt class="text-body-secondary fw-normal">Profesional de referencia</dt>
                        <dd class="mb-0">
                            @if($referenciaVigente)
                                <span class="fw-semibold">{{ $referenciaVigente->profesional?->nombre_completo }}</span>
                                <span class="d-block text-body-secondary">
                                    {{ $referenciaVigente->origen?->label() }} · desde el {{ $referenciaVigente->fecha_inicio?->format('d/m/Y') }}
                                </span>
                            @else
                                Sin profesional de referencia
                            @endif
                        </dd>
                    @endif
                </dl>

                @if($historialAsig > 0)
                    <button type="button"
                            class="btn btn-link btn-sm p-0 mt-2 d-inline-flex align-items-center gap-1 collapsed"
                            data-bs-toggle="collapse" data-bs-target="#asignaciones-historial"
                            aria-expanded="false" aria-controls="asignaciones-historial">
                        Historial ({{ $historialAsig }})
                        <x-heroicon-o-chevron-down class="icon-12 op-toggle-icon" aria-hidden="true"/>
                    </button>
                    <div class="collapse" id="asignaciones-historial" wire:ignore.self>
                        <ul class="list-unstyled small mt-2 mb-0">
                            @foreach($centrosAsig->whereNotNull('fecha_fin') as $asig)
                                <li class="mb-1">
                                    {{ $asig->centro?->nombre }} <span class="text-body-secondary">({{ $asig->modo->label() }})</span>
                                    <span class="d-block text-body-secondary">{{ $asig->fecha_inicio->format('d/m/Y') }} – {{ $asig->fecha_fin->format('d/m/Y') }}</span>
                                </li>
                            @endforeach
                            @foreach($referencias->whereNotNull('fecha_fin') as $asig)
                                <li class="mb-1">
                                    Referencia: {{ $asig->profesional?->nombre_completo }} <span class="text-body-secondary">({{ $asig->origen?->label() }})</span>
                                    <span class="d-block text-body-secondary">{{ $asig->fecha_inicio?->format('d/m/Y') }} – {{ $asig->fecha_fin->format('d/m/Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- ——— Otras prestaciones ——— --}}
            @if($prestaciones->isNotEmpty())
                <div class="card card-body mb-3">
                    <h2 class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
                        <x-heroicon-o-squares-2x2 class="icon-16" aria-hidden="true"/>
                        Otras prestaciones
                    </h2>
                    <div class="list-group list-group-flush">
                        @foreach($prestaciones as $pres)
                        @php
                            $estadoLabel = match($pres->estado) {
                                'activo'     => 'Activo',
                                'en_tramite' => 'En trámite',
                                'finalizado' => 'Finalizado',
                                'denegado'   => 'Denegado',
                                'baja'       => 'Baja',
                                default      => $pres->estado,
                            };
                        @endphp
                        <div class="list-group-item d-flex align-items-start justify-content-between gap-2 px-0">
                            <div>
                                <div class="small fw-semibold">{{ $pres->descripcion }}</div>
                                <div class="small text-body-secondary">{{ $pres->fecha_inicio?->format('d/m/Y') }}</div>
                            </div>
                            <span class="badge {{ Tonos::estadoPrestacion($pres->estado)->clasesSuave() }}">{{ $estadoLabel }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ——— Historial de atenciones ——— --}}
            @if($this->historialAtenciones->isNotEmpty() || $this->puedeCrearAtencion)
            <div class="card mb-3" id="ficha-atencion-historial">
                <div class="card-header d-flex align-items-center gap-2">
                    <x-heroicon-o-arrow-path class="icon-14" aria-hidden="true"/>
                    <h2 class="h6 fw-semibold mb-0">Historial de atenciones</h2>
                    <span class="badge rounded-pill text-bg-secondary">{{ $this->historialAtenciones->count() }}</span>
                </div>

                @if($this->historialAtenciones->isEmpty())
                    <div class="card-body small text-body-secondary">Sin atenciones registradas.</div>
                @else
                <div class="list-group list-group-flush">
                    @foreach($this->historialAtenciones as $registro)
                    @php
                        $tipoLabel = match($registro->tipo) {
                            'informacion' => 'Información',
                            'actividad'   => 'Actividad',
                            'contacto'    => 'Contacto',
                            default       => $registro->tipo,
                        };
                    @endphp
                    <div class="list-group-item" wire:key="ra-{{ $registro->id }}">
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="fw-semibold">{{ $registro->fecha->format('d/m/Y') }}</span>
                            <span class="badge {{ Tonos::tipoRegistroAtencion($registro->tipo)->clasesSuave() }}">{{ $tipoLabel }}</span>
                            @if($registro->profesional)
                            <span class="text-body-secondary">{{ $registro->profesional->name }}</span>
                            @endif
                            @if($registro->prestacion)
                            <span class="text-body-secondary">{{ $registro->prestacion->nombre }}</span>
                            @endif
                        </div>
                        <div class="small mt-1">
                            {{ $registro->resumenHistorial() }}
                        </div>
                        @if($registro->demanda || $registro->respuesta)
                        <button
                            type="button"
                            class="btn btn-link btn-sm p-0 d-inline-flex align-items-center gap-1 collapsed"
                            data-bs-toggle="collapse"
                            data-bs-target="#atencion-{{ $registro->id }}"
                            aria-expanded="false"
                            aria-controls="atencion-{{ $registro->id }}"
                        >
                            Detalle
                            <x-heroicon-o-chevron-down class="icon-12 op-toggle-icon" aria-hidden="true"/>
                        </button>
                        <div class="collapse" id="atencion-{{ $registro->id }}">
                            <dl class="small mt-2 mb-0">
                                @if($registro->demanda)
                                <dt class="text-uppercase text-body-secondary">Demanda</dt>
                                <dd>{{ $registro->demanda }}</dd>
                                @endif
                                @if($registro->respuesta)
                                <dt class="text-uppercase text-body-secondary">Respuesta</dt>
                                <dd class="mb-0">{{ $registro->respuesta }}</dd>
                                @endif
                            </dl>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

        </div>{{-- /col-lg-4 --}}

    </div>{{-- /row --}}
</div>

{{-- ===== MODAL RELACIÓN ===== --}}
@if($modalRelacionAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-relacion-titulo"
     wire:click.self="cerrarModalRelacion">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h2 id="modal-relacion-titulo" class="modal-title fs-6">
                    {{ $relacionId ? 'Editar relación' : 'Nueva relación' }}
                </h2>
                <button wire:click="cerrarModalRelacion" type="button" class="btn-close" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                {{-- Tipo de relación (solo en creación) --}}
                @if(! $relacionId)
                <div class="mb-3">
                    <label for="relacion-tipo" class="form-label small fw-semibold">
                        Tipo de relación <span class="text-danger">*</span>
                    </label>
                    <select id="relacion-tipo" wire:model="relacionTipo" class="form-select form-select-sm @error('relacionTipo') is-invalid @enderror">
                        <option value="">— Seleccionar —</option>
                        @foreach($this->tiposRelacion as $slug => $etiqueta)
                            <option value="{{ $slug }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('relacionTipo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Buscador ciudadano (solo en creación) --}}
                <div class="mb-3">
                    <label for="relacion-busqueda" class="form-label small fw-semibold">
                        Ciudadano <span class="text-danger">*</span>
                    </label>
                    @if($this->ciudadanoSeleccionadoRelacion)
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">{{ $this->ciudadanoSeleccionadoRelacion->nombre_completo }}</span>
                            <button type="button" wire:click="$set('relacionCiudadanoSeleccionado', null)"
                                class="btn btn-sm btn-outline-secondary p-1" aria-label="Quitar ciudadano seleccionado">
                                <x-heroicon-o-x-mark class="icon-14" aria-hidden="true"/>
                            </button>
                        </div>
                    @else
                        <input id="relacion-busqueda" type="text" wire:model.live="relacionBusqueda"
                            placeholder="Escribir nombre (mín. 2 caracteres)…"
                            class="form-control form-control-sm">
                        @if($this->relacionResultadosBusqueda->isNotEmpty())
                            <div class="list-group mt-1">
                                @foreach($this->relacionResultadosBusqueda as $sug)
                                    <button type="button" wire:click="seleccionarCiudadanoRelacion({{ $sug->id }})"
                                        class="list-group-item list-group-item-action small">
                                        {{ $sug->nombre_completo }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    @error('relacionCiudadanoSeleccionado')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Fecha inicio (solo en creación) --}}
                <div class="mb-3">
                    <label for="relacion-fecha" class="form-label small fw-semibold">
                        Fecha de inicio <span class="text-danger">*</span>
                    </label>
                    <input id="relacion-fecha" type="date" wire:model="relacionFechaInicio"
                        class="form-control form-control-sm @error('relacionFechaInicio') is-invalid @enderror">
                    @error('relacionFechaInicio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endif

                {{-- Observaciones (creación y edición) --}}
                <div>
                    <label for="relacion-observaciones" class="form-label small fw-semibold">Observaciones</label>
                    <textarea id="relacion-observaciones" wire:model="relacionObservaciones" rows="3" placeholder="Opcional…"
                        class="form-control form-control-sm @error('relacionObservaciones') is-invalid @enderror"></textarea>
                    @error('relacionObservaciones')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <div>
                    @if($relacionId)
                        <button wire:click="cerrarRelacion({{ $relacionId }})" type="button"
                            wire:confirm="¿Confirmar el cierre de esta relación? Se establecerá fecha de fin hoy."
                            class="btn btn-sm btn-outline-danger">
                            Cerrar relación
                        </button>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <button wire:click="cerrarModalRelacion" type="button" class="btn btn-outline-secondary btn-sm">
                        Cancelar
                    </button>
                    <button wire:click="guardarRelacion" type="button" class="btn btn-primary btn-sm">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ===== MODAL NUEVO DOCUMENTO ===== --}}
@if($modalDocumento)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-documento-titulo"
     wire:click.self="cerrarModalDocumento">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h2 id="modal-documento-titulo" class="modal-title fs-6">Añadir documento de identidad</h2>
                <button wire:click="cerrarModalDocumento" type="button" class="btn-close" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary">
                    El documento actual recibirá fecha de fin. El historial se conserva íntegro.
                </p>

                <div class="mb-3">
                    <label for="documento-tipo" class="form-label small fw-semibold">Tipo de documento</label>
                    <select id="documento-tipo" wire:model="nuevoTipoDocumento" class="form-select form-select-sm">
                        <option value="nif">DNI / NIF</option>
                        <option value="nie">NIE</option>
                        <option value="pasaporte">Pasaporte</option>
                    </select>
                </div>
                <div>
                    <label for="documento-valor" class="form-label small fw-semibold">Número de documento</label>
                    <input id="documento-valor" type="text" wire:model="nuevoValorDocumento" placeholder="Ej.: 12345678A"
                        class="form-control form-control-sm font-monospace @error('nuevoValorDocumento') is-invalid @enderror">
                    @error('nuevoValorDocumento')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="cerrarModalDocumento" type="button" class="btn btn-outline-secondary btn-sm">
                    Cancelar
                </button>
                <button wire:click="guardarDocumento" type="button" class="btn btn-primary btn-sm">
                    Guardar documento
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
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

                <div>
                    <label class="form-label small fw-semibold" for="at-fecha">Fecha</label>
                    <input
                        type="date"
                        id="at-fecha"
                        wire:model="atencionFecha"
                        class="form-control form-control-sm @error('atencionFecha') is-invalid @enderror"
                        max="{{ now()->toDateString() }}"
                    >
                    @error('atencionFecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if(! auth()->user()->hasRole('consulta_basica'))
                <fieldset>
                    <legend class="form-label small fw-semibold">Tipo de atención</legend>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="at-tipo-informacion" wire:model="atencionTipo" value="informacion">
                        <label class="form-check-label" for="at-tipo-informacion">Información / orientación</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="at-tipo-contacto" wire:model="atencionTipo" value="contacto">
                        <label class="form-check-label" for="at-tipo-contacto">Contacto (llamada, email…)</label>
                    </div>
                </fieldset>
                @endif

                <div>
                    <label class="form-label small fw-semibold" for="at-demanda">Demanda del ciudadano</label>
                    <textarea
                        id="at-demanda"
                        wire:model="atencionDemanda"
                        class="form-control form-control-sm @error('atencionDemanda') is-invalid @enderror"
                        rows="3"
                        placeholder="Qué solicita o comunica el ciudadano…"
                    ></textarea>
                    @error('atencionDemanda') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @include('intervencion::partials.vincular-cita')

                <div>
                    <label class="form-label small fw-semibold" for="at-respuesta">Respuesta / actuación</label>
                    <textarea
                        id="at-respuesta"
                        wire:model="atencionRespuesta"
                        class="form-control form-control-sm"
                        rows="2"
                        placeholder="Qué se le informa, orienta o tramita…"
                    ></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button wire:click="cerrarModalAtencion" class="btn btn-outline-secondary btn-sm" type="button">Cancelar</button>
                <button wire:click="guardarAtencion" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button">
                    <x-heroicon-o-check class="icon-14" aria-hidden="true"/>
                    Guardar atención
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

</div>
