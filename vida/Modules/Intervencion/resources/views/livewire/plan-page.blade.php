@use('Modules\Intervencion\Support\Ui\Tonos')
<div
    class="op-page d-flex flex-column"
    x-data="{ seccionActiva: '' }"
    x-on:keydown.escape.window="
        $wire.drawerAbierto && $wire.cerrarDrawer();
        $wire.modalMotivoAbierto && $wire.cancelarCambio();
    "
>

{{-- ============================================================
     BREADCRUMB + ACCIONES (sticky)
     ============================================================ --}}
<div class="sticky-top d-flex align-items-center gap-3 px-3 py-2 bg-body border-bottom">
    <nav aria-label="Ubicación">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item">
                <a href="{{ route('intervencion.ciudadano.show', $this->plan?->historia_id ?? $this->historiaId) }}"
                   wire:navigate>{{ $this->ciudadano?->nombre_completo ?? 'Intervención' }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Plan de intervención</li>
        </ol>
    </nav>

    <div class="d-flex gap-2 align-items-center ms-auto">
        @if($this->plan)
        @include('mensajes::partials.boton-escribir-mensaje', ['tipo' => 'plan', 'id' => $this->plan->id])
        <span class="badge rounded-pill {{ $this->plan->estado->tono()->clasesSuave() }}">
            {{ $this->plan->estado->label() }}
        </span>
        <span class="badge rounded-pill bg-body-tertiary text-body-secondary border">v{{ $this->plan->version }}</span>
        <button wire:click="generarPdf" class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-arrow-down-tray class="icon-14"/>
            Generar PDF
        </button>
        @endif

        @if($this->plan?->estado->value === 'borrador')
        <button
            wire:click="activarPlan"
            class="btn btn-primary btn-sm"
            @if(! $this->puedeActivarse) disabled title="Marca ambas firmas para activar" @endif
        >
            <x-heroicon-o-check class="icon-14"/>
            Activar plan
        </button>
        @endif

        @if($this->plan?->estado->value === 'activo')
        <button wire:click="abrirModalCierre" class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-x-circle class="icon-14"/>
            Cerrar plan
        </button>
        @endif
    </div>
</div>

{{-- Mensaje de éxito --}}
@if($mensajeExito)
<div class="alert alert-success d-flex align-items-center gap-2 py-2 rounded-0 mb-0 border-0 border-bottom"
     x-init="setTimeout(() => $wire.set('mensajeExito', ''), 3000)">
    <x-heroicon-o-check-circle class="icon-14"/>
    {{ $mensajeExito }}
</div>
@endif

{{-- ============================================================
     CUERPO + ÍNDICE
     ============================================================ --}}
<div class="row g-3 align-items-start mx-0 p-3 p-lg-4">
<div class="col d-flex flex-column gap-3">

    {{-- SECCIÓN 0: Datos de la persona --}}
    <div class="card" id="ps-datos"         x-on:focusin="seccionActiva = 'datos'"         x-on:click="seccionActiva = 'datos'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-user class="icon-16"/>
                Datos de la persona
            </div>
            <span class="badge rounded-pill border text-secondary fw-normal">Solo lectura · Historia Social</span>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 g-2 mx-0 p-2 rounded bg-body-tertiary">
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Nombre completo</div>
                    {{ $this->ciudadano?->nombre_completo ?? '—' }}
                </div>
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Fecha de nacimiento</div>
                    {{ $this->ciudadano?->fecha_nacimiento ? \Carbon\Carbon::parse($this->ciudadano->fecha_nacimiento)->format('d/m/Y') : '—' }}
                </div>
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Documento</div>
                    @php $doc = $this->ciudadano?->documentoVigente; @endphp
                    {{ $doc ? strtoupper($doc->tipo).' '.$doc->valor : '—' }}
                </div>
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Domicilio</div>
                    {{ $this->ciudadano?->direccion_texto ?? '—' }}
                </div>
                @if($this->ciudadano?->telefono)
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Teléfono</div>
                    {{ $this->ciudadano->telefono }}
                </div>
                @endif
                @if($this->ciudadano?->email)
                <div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-1">Correo electrónico</div>
                    {{ $this->ciudadano->email }}
                </div>
                @endif
            </div>

            @if($this->miembrosUc->isNotEmpty())
            <div class="mt-3 pt-3 border-top">
                <div class="small text-uppercase fw-semibold text-body-secondary mb-2">Unidad de convivencia</div>
                @foreach($this->miembrosUc as $m)
                <span class="badge rounded-pill border border-secondary-subtle text-body fw-normal me-1 mb-1">
                    {{ $m['ciudadano']->nombre_completo }}
                    @if($m['relacion'])
                    <span class="text-secondary ms-1">{{ $m['relacion'] }}</span>
                    @endif
                </span>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SELECTOR TIPO DE PLAN (solo visible en modo creación) --}}
    @if(! $this->plan)
    <div class="card border-primary border-2" id="ps-tipo-plan">
        <div class="card-header bg-transparent d-flex align-items-center gap-2">
            <x-heroicon-o-clipboard-document-list class="icon-16 text-primary"/>
            <span class="d-flex align-items-center gap-2 fw-semibold">Tipo de plan</span>
            <span class="badge bg-primary-subtle text-primary ms-auto">Obligatorio</span>
        </div>
        <div class="card-body">
            <label class="form-label small fw-semibold" for="tipo-plan-select">
                Elige el tipo de plan para comenzar
            </label>
            <select id="tipo-plan-select"
                    wire:model.live="tipoPlanId"
                    class="form-select @error('tipoPlanId') is-invalid @enderror"
                    wire:loading.attr="disabled">
                <option value="">— Selecciona un tipo —</option>
                @foreach($this->tiposPlanes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
            <div class="form-text" wire:loading wire:target="updatedTipoPlanId">
                Creando borrador…
            </div>
            @error('tipoPlanId')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    @endif

    {{-- SECCIÓN 1: Diagnóstico social --}}
    <div class="card" id="ps-diagnostico" x-on:focusin="seccionActiva = 'diagnostico'" x-on:click="seccionActiva = 'diagnostico'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-document-text class="icon-16"/>
                Diagnóstico social
            </div>
            <button wire:click="abrirDrawer" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-circle-stack class="icon-14"/>
                Añadir fichas
            </button>
        </div>
        <div class="card-body">

            {{-- Bloque A: Evidencia de fichas --}}
            <div class="d-flex flex-column gap-2 mb-4">
                @forelse($this->fichasDiagnostico as $pfd)
                <div class="card" wire:key="pfd-{{ $pfd->id }}">
                    <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
                        <button type="button"
                                class="btn btn-link p-0 text-start text-decoration-none d-flex align-items-center gap-2 flex-fill text-secondary small fw-semibold collapsed"
                                data-bs-toggle="collapse"
                                data-bs-target="#pfd-content-{{ $pfd->id }}"
                                aria-expanded="false"
                                aria-controls="pfd-content-{{ $pfd->id }}">
                            <x-heroicon-o-lock-closed class="icon-12 opacity-50"/>
                            {{ $pfd->ficha?->tipoFicha?->nombre ?? 'Ficha' }}
                            <span class="text-secondary fw-normal ms-1">
                                {{ $pfd->ficha?->created_at?->format('d/m/Y') }}
                            </span>
                            <x-heroicon-o-chevron-down class="icon-12 op-toggle-icon ms-auto"/>
                        </button>
                        <button
                            wire:click="eliminarFichaDiagnostico({{ $pfd->ficha_id }})"
                            class="btn btn-outline-danger btn-sm p-1 lh-1 flex-shrink-0"
                            title="Eliminar del diagnóstico"
                        >
                            <x-heroicon-o-x-mark class="icon-12"/>
                        </button>
                    </div>
                    <div class="collapse" id="pfd-content-{{ $pfd->id }}">
                    <div class="card-body py-2 px-3">
                        @php $datos = $pfd->ficha?->datos ?? [] @endphp
                        @forelse($datos as $campo => $valor)
                        <div class="d-flex gap-2 mb-1">
                            <span class="fw-semibold text-nowrap">{{ $campo }}</span>
                            <span class="text-secondary">{{ is_array($valor) ? implode(', ', $valor) : $valor }}</span>
                        </div>
                        @empty
                        <span class="text-body-secondary">Sin datos registrados.</span>
                        @endforelse
                    </div>
                    </div>
                </div>
                @empty
                <p class="text-body-secondary mb-0">
                    Ninguna ficha añadida aún.
                    <button wire:click="abrirDrawer" class="btn btn-link btn-sm p-0 align-baseline">Añadir fichas del historial</button>
                </p>
                @endforelse

                @if($this->fichasDiagnostico->isNotEmpty())
                <button wire:click="abrirDrawer" class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                    <x-heroicon-o-plus class="icon-14"/>
                    Añadir otra ficha
                </button>
                @endif
            </div>

            {{-- Bloque B: Síntesis profesional --}}
            <div>
                <div class="d-flex align-items-center gap-1 small text-secondary mb-2">
                    <x-heroicon-o-pencil class="icon-14"/>
                    Síntesis profesional
                </div>
                <div class="btn-toolbar gap-1 p-1 border border-bottom-0 rounded-top bg-body-tertiary" role="toolbar" aria-label="Formato del texto">
                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('bold')"
                            title="Negrita"><strong>B</strong></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('italic')"
                            title="Cursiva"><em>I</em></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('insertUnorderedList')"
                            title="Lista">
                        <x-heroicon-o-list-bullet class="icon-14"/>
                    </button>
                </div>
                <div
                    class="form-control rounded-top-0 plan-editor-area"
                    contenteditable="{{ $this->plan?->estado !== 'cerrado' ? 'true' : 'false' }}"
                    x-data
                    x-on:blur="$wire.guardarDiagnosticoDesdeEditor($el.innerHTML)"
                >{!! $diagnosticoTexto !!}</div>
            </div>

        </div>
    </div>

    {{-- SECCIÓN 2: Objetivos --}}
    <div class="card" id="ps-objetivos"     x-on:focusin="seccionActiva = 'objetivos'"     x-on:click="seccionActiva = 'objetivos'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-viewfinder-circle class="icon-16"/>
                Objetivos
            </div>
            <button wire:click="abrirModalObjetivo" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14"/>
                Añadir objetivo
            </button>
        </div>
        <div class="card-body">
            @php
                $tieneObjetivos = $this->objetivosConIndicadores->isNotEmpty()
                    || $this->objetivosEspecificosIndependientes->isNotEmpty();
            @endphp
            @if(! $tieneObjetivos)
            <p class="text-body-secondary fst-italic mb-0">Ningún objetivo definido aún.</p>
            @else

            {{-- Objetivos generales --}}
            @if($this->objetivosConIndicadores->isNotEmpty())
            <div class="row row-cols-1 row-cols-lg-2 g-3 mb-3">
                @foreach($this->objetivosConIndicadores as $og)
                <div class="col" wire:key="og-{{ $og->id }}">
                <div class="card h-100">
                    <div class="card-body pb-2">
                        <p class="mb-2">{{ $og->texto }}</p>

                        @if($og->indicador)
                        <div class="mt-2 p-2 rounded bg-body-tertiary border-start border-2" wire:key="ind-og-{{ $og->indicador->id }}">
                            <div class="small fst-italic text-body-secondary mb-1">{{ $og->indicador->descripcion }}</div>
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach($og->indicador->valoresPosibles() as $valor => $etiqueta)
                                <input
                                    type="radio"
                                    class="btn-check"
                                    name="indicador-{{ $og->indicador->id }}"
                                    id="indicador-{{ $og->indicador->id }}-{{ $valor }}"
                                    autocomplete="off"
                                    wire:click="guardarValoracionIndicador({{ $og->indicador->id }}, '{{ $valor }}')"
                                    {{ $og->indicador->valoracion_actual === $valor ? 'checked' : '' }}
                                    @if($this->plan?->estado->value === 'cerrado') disabled @endif
                                >
                                <label for="indicador-{{ $og->indicador->id }}-{{ $valor }}"
                                       class="btn btn-sm rounded-pill py-0 {{ $og->indicador->valoracion_actual === $valor ? 'btn-dark' : 'btn-outline-secondary' }}">
                                    {{ $etiqueta }}
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button wire:click="abrirEditarObjetivo({{ $og->id }})" class="btn btn-outline-secondary btn-sm">
                            <x-heroicon-o-pencil-square class="icon-14"/>
                            Editar
                        </button>
                    </div>
                </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Objetivos específicos independientes --}}
            @if($this->objetivosEspecificosIndependientes->isNotEmpty())
            @if($this->objetivosConIndicadores->isNotEmpty())
            <div class="small text-secondary fw-semibold text-uppercase mb-2">Objetivos específicos</div>
            @endif
            <div class="row row-cols-1 row-cols-lg-2 g-3">
                @foreach($this->objetivosEspecificosIndependientes as $oe)
                <div class="col" wire:key="oe-ind-{{ $oe->id }}">
                <div class="card h-100">
                    <div class="card-body pb-2">
                        @if($oe->tipoFicha)
                        <div class="badge bg-primary-subtle text-primary-emphasis font-monospace fw-normal mb-1">{{ $oe->tipoFicha->nombre }}</div>
                        @endif
                        <p class="mb-2">{{ $oe->texto }}</p>

                        @if($oe->indicador)
                        <div class="mt-2 ms-2 p-2 rounded bg-body-tertiary border-start border-2 border-primary" wire:key="ind-oe-ind-{{ $oe->indicador->id }}">
                            <div class="small fst-italic text-body-secondary mb-1">{{ $oe->indicador->descripcion }}</div>
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach($oe->indicador->valoresPosibles() as $valor => $etiqueta)
                                <input
                                    type="radio"
                                    class="btn-check"
                                    name="indicador-{{ $oe->indicador->id }}"
                                    id="indicador-{{ $oe->indicador->id }}-{{ $valor }}"
                                    autocomplete="off"
                                    wire:click="guardarValoracionIndicador({{ $oe->indicador->id }}, '{{ $valor }}')"
                                    {{ $oe->indicador->valoracion_actual === $valor ? 'checked' : '' }}
                                    @if($this->plan?->estado->value === 'cerrado') disabled @endif
                                >
                                <label for="indicador-{{ $oe->indicador->id }}-{{ $valor }}"
                                       class="btn btn-sm rounded-pill py-0 {{ $oe->indicador->valoracion_actual === $valor ? 'btn-dark' : 'btn-outline-secondary' }}">
                                    {{ $etiqueta }}
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button wire:click="abrirEditarObjetivo({{ $oe->id }})" class="btn btn-outline-secondary btn-sm">
                            <x-heroicon-o-pencil-square class="icon-14"/>
                            Editar
                        </button>
                    </div>
                </div>
                </div>
                @endforeach
            </div>
            @endif

            @endif
        </div>
    </div>

    {{-- SECCIÓN 3: Compromisos del Ayuntamiento --}}
    <div class="card" id="ps-ayto"          x-on:focusin="seccionActiva = 'ayto'"          x-on:click="seccionActiva = 'ayto'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-building-office class="icon-16"/>
                Compromisos del Ayuntamiento
            </div>
            <button wire:click="abrirModalActuacionAyto" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14"/>
                Añadir
            </button>
        </div>
        <div class="card-body p-0">
            @if($this->actuacionesAyuntamiento->isEmpty())
            <p class="text-body-secondary fst-italic p-4 mb-0">Ninguna actuación definida.</p>
            @else
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Prestación</th>
                        <th>Concreción</th>
                        <th>Responsable</th>
                        <th>Inicio previsto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->actuacionesAyuntamiento as $act)
                    <tr wire:key="aact-{{ $act->id }}">
                        <td>
                            <div class="fw-medium">{{ $act->prestacion->nombre }}</div>
                            <div class="font-monospace small text-secondary">{{ $act->prestacion->codigo }}</div>
                        </td>
                        <td class="text-secondary">{{ $act->descripcion_especifica ?? '—' }}</td>
                        <td>
                            @if($act->responsable)
                            <x-avatar pequeno :nombre="$act->responsable->nombre_completo" />
                            @else —
                            @endif
                        </td>
                        <td class="text-secondary">{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="badge rounded-pill {{ Tonos::estadoActuacion($act->estado)->clasesSuave() }}">{{ ucfirst($act->estado) }}</span></td>
                        <td><button wire:click="abrirEditarActuacionAyto({{ $act->id }})" class="btn btn-outline-secondary btn-sm"><x-heroicon-o-pencil-square class="icon-14"/> Editar</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table></div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 4: Compromisos del ciudadano --}}
    <div class="card" id="ps-ciudadano"    x-on:focusin="seccionActiva = 'ciudadano'"    x-on:click="seccionActiva = 'ciudadano'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-check-badge class="icon-16"/>
                Compromisos de la persona
            </div>
            <button wire:click="abrirModalCompromiso" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14"/>
                Añadir
            </button>
        </div>
        <div class="card-body p-0">
            @if($this->actuacionesCiudadano->isEmpty())
            <p class="text-body-secondary fst-italic p-4 mb-0">Ningún compromiso definido.</p>
            @else
            <ul class="list-group list-group-flush">
                @foreach($this->actuacionesCiudadano as $act)
                <li class="list-group-item d-flex align-items-start gap-3 py-3 px-4" wire:key="aciu-{{ $act->id }}">
                    <x-heroicon-o-check-circle class="icon-16 text-success flex-shrink-0 mt-1"/>
                    <div class="flex-fill">
                        <div>{{ $act->descripcion }}</div>
                        @if($act->prestacion)
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal mt-1">{{ $act->prestacion->nombre }}</span>
                        @endif
                    </div>
                    <button wire:click="abrirEditarCompromisoCiudadano({{ $act->id }})" class="btn btn-outline-secondary btn-sm ms-auto flex-shrink-0">
                        <x-heroicon-o-pencil-square class="icon-14"/>
                        Editar
                    </button>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 5: Participantes --}}
    <div class="card" id="ps-participantes" x-on:focusin="seccionActiva = 'participantes'" x-on:click="seccionActiva = 'participantes'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-users class="icon-16"/>
                Profesionales participantes
            </div>
            <button wire:click="abrirModalParticipante" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-14"/>
                Añadir
            </button>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach($this->participantes as $p)
                <li class="list-group-item d-flex align-items-center gap-3 py-3 px-4" wire:key="part-{{ $p->id }}">
                    <x-avatar pequeno :nombre="$p->profesional->nombre_completo" />
                    <div class="flex-fill">
                        <div class="fw-medium">{{ $p->profesional->nombre_completo }}</div>
                        <div class="small text-secondary">
                            {{ $p->rol_en_plan }}
                            @if($p->servicio) · {{ $p->servicio->nombre }} @endif
                        </div>
                    </div>
                    @if($p->user_id === $this->plan?->profesional_responsable_id)
                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Responsable</span>
                    @else
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1">
                        <x-heroicon-o-x-mark class="icon-14"/>
                    </button>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- SECCIÓN 6: Seguimiento y firmas --}}
    <div class="card" id="ps-firmas"        x-on:focusin="seccionActiva = 'firmas'"        x-on:click="seccionActiva = 'firmas'">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 fw-semibold">
                <x-heroicon-o-pencil class="icon-16"/>
                Seguimiento y firmas
            </div>
        </div>
        <div class="card-body">

            {{-- Condiciones de seguimiento --}}
            <p class="text-uppercase text-secondary fw-semibold small mb-3">Condiciones de seguimiento</p>
            <div class="row g-3">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small">Frecuencia de seguimiento</label>
                    <select
                        wire:model.live="periodicidadSeguimiento"
                        class="form-select form-select-sm"
                    >
                        <option value="mensual">Mensual</option>
                        <option value="bimestral">Bimestral</option>
                        <option value="trimestral">Trimestral</option>
                        <option value="semestral">Semestral</option>
                        <option value="anual">Anual</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small">Observaciones sobre el seguimiento</label>
                    <textarea
                        wire:model.lazy="observacionesSeguimiento"
                        class="form-control form-control-sm"
                        rows="2"
                        placeholder="Acuerdos sobre el seguimiento, condiciones especiales…"
                    ></textarea>
                </div>
            </div>

            <hr class="my-4">

            {{-- Firmas --}}
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold mb-1">{{ $this->plan?->profesionalResponsable?->nombre_completo }}</div>
                        <div class="small text-secondary mb-3">Profesional responsable</div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="firma-profesional"
                                wire:model.live="profesionalFirmado"
                                wire:change="marcarFirmaProfesional($event.target.checked)"
                                @if($this->plan?->estado->value === 'cerrado') disabled @endif
                            >
                            <label class="form-check-label" for="firma-profesional">Ha firmado en papel</label>
                        </div>
                        @if($profesionalFirmado)
                        <div class="form-text">
                            Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('profesional_firmado_en')?->format('d/m/Y H:i') }}
                        </div>
                        @endif
                    </div>
                </div>
                </div>

                <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-semibold mb-1">{{ $this->ciudadano?->nombre_completo }}</div>
                        <div class="small text-secondary mb-3">Persona interesada</div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="firma-ciudadano"
                                wire:model.live="ciudadanoFirmado"
                                wire:change="marcarFirmaCiudadano($event.target.checked)"
                                @if($this->plan?->estado->value === 'cerrado') disabled @endif
                            >
                            <label class="form-check-label" for="firma-ciudadano">Ha firmado en papel</label>
                        </div>
                        @if($ciudadanoFirmado)
                        <div class="form-text">
                            Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('ciudadano_firmado_en')?->format('d/m/Y H:i') }}
                        </div>
                        @endif
                    </div>
                </div>
                </div>
            </div>

            {{-- Fecha de firma presencial --}}
            <div class="mt-3">
                <label class="form-label small">Fecha de la firma presencial</label>
                <input
                    type="date"
                    wire:model.lazy="fechaFirmaPresencial"
                    wire:change="guardarFechaFirma"
                    class="form-control form-control-sm w-auto"
                >
            </div>

            @if($this->puedeActivarse)
            <div class="alert alert-success d-flex align-items-center gap-2 mt-3">
                <x-heroicon-o-check-circle class="icon-14"/>
                Ambas partes han firmado. El plan puede activarse desde el botón superior.
            </div>
            @endif

            <p class="small text-secondary mt-3 mb-0">
                Una vez activado el plan, cualquier cambio requerirá indicar el motivo.
                El PDF puede generarse en cualquier momento desde el botón superior.
            </p>

        </div>
    </div>

    {{-- BOTÓN PRINCIPAL --}}
    @if($this->plan && $this->plan->estado->value !== 'cerrado')
    <div class="d-flex justify-content-end py-2">
        <button wire:click="guardarPlan" class="btn btn-primary">
            <x-heroicon-o-check class="icon-14"/>
            Guardar plan
        </button>
    </div>
    @endif

</div>{{-- fin plan-body --}}

{{-- ÍNDICE LATERAL --}}
@php
$plan = $this->plan;
$nc = [
    'datos'         => true,
    'diagnostico'   => (bool) ($plan?->diagnostico_social || $this->fichasDiagnostico->isNotEmpty()),
    'objetivos'     => $plan?->objetivosGenerales->isNotEmpty() ?? false,
    'ayto'          => $plan?->actuacionesAyuntamiento->isNotEmpty() ?? false,
    'ciudadano'     => $plan?->actuacionesCiudadano->isNotEmpty() ?? false,
    'participantes' => $plan?->participantesActivos->isNotEmpty() ?? false,
    'firmas'        => $profesionalFirmado || $ciudadanoFirmado,
];
@endphp
<nav class="col-auto d-none d-xl-flex flex-column gap-1 plan-index" aria-label="Secciones del plan" x-on:click.stop>
    <div class="small text-uppercase text-secondary fw-semibold mb-2 px-2">Secciones</div>

    @foreach([
        'datos'         => 'Datos',
        'diagnostico'   => 'Diagnóstico',
        'objetivos'     => 'Objetivos',
        'ayto'          => 'Ayuntamiento',
        'ciudadano'     => 'Ciudadano',
        'participantes' => 'Participantes',
        'firmas'        => 'Firmas',
    ] as $id => $label)
    <a href="#ps-{{ $id }}"
       class="nav-link link-secondary rounded small d-flex align-items-center gap-2 py-1 px-2"
       x-on:click="seccionActiva = '{{ $id }}'">
        <span class="d-inline-block rounded-circle p-1 flex-shrink-0"
              :class="seccionActiva === '{{ $id }}' ? 'bg-primary' : (@json($nc[$id]) ? 'bg-success' : 'bg-secondary-subtle')"></span>
        {{ $label }}
    </a>
    @endforeach

    <div class="mt-4 px-2">
        <div class="small text-uppercase text-secondary">Seguimiento</div>
        <div class="fw-semibold small">{{ ucfirst($periodicidadSeguimiento) }}</div>
    </div>
</nav>
</div>{{-- fin plan-body-wrap --}}

{{-- ============================================================
     MODAL: AÑADIR FICHAS DEL HISTORIAL
     ============================================================ --}}
@if($drawerAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fichas del historial social</h5>
                <button type="button" class="btn-close" wire:click="cerrarDrawer" aria-label="Cerrar"></button>
            </div>
            <div class="px-3 py-2 border-bottom d-flex gap-2 flex-wrap">
                <button wire:click="$set('drawerFiltroFecha','todas')"
                    class="btn btn-sm rounded-pill {{ $drawerFiltroFecha === 'todas' ? 'btn-secondary' : 'btn-outline-secondary' }}">Todas</button>
                <button wire:click="$set('drawerFiltroFecha','mes')"
                    class="btn btn-sm rounded-pill {{ $drawerFiltroFecha === 'mes' ? 'btn-secondary' : 'btn-outline-secondary' }}">Último mes</button>
                <button wire:click="$set('drawerFiltroFecha','anio')"
                    class="btn btn-sm rounded-pill {{ $drawerFiltroFecha === 'anio' ? 'btn-secondary' : 'btn-outline-secondary' }}">Último año</button>
            </div>
            <div class="modal-body p-0">
                @forelse($this->fichasHistorial as $ficha)
                <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom"
                     wire:key="df-{{ $ficha->id }}">
                    <input class="form-check-input mt-0 flex-shrink-0"
                           type="checkbox"
                           id="df{{ $ficha->id }}"
                           wire:model="fichasSeleccionadas"
                           value="{{ $ficha->id }}">
                    <label class="form-check-label flex-fill" for="df{{ $ficha->id }}">
                        <span class="d-block fw-medium small">{{ $ficha->tipoFicha?->nombre ?? 'Ficha' }}</span>
                        <span class="small text-secondary">{{ $ficha->created_at->format('d/m/Y') }}</span>
                    </label>
                    @if(in_array($ficha->id, $fichasSeleccionadas))
                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal flex-shrink-0">Añadida</span>
                    @endif
                </div>
                @empty
                <p class="text-body-secondary fst-italic p-4 mb-0">No hay fichas registradas en el historial social.</p>
                @endforelse
            </div>
            <div class="modal-footer">
                <button wire:click="cerrarDrawer" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="aplicarSeleccionFichas" class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Aplicar selección
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL DE MOTIVO OBLIGATORIO
     ============================================================ --}}
@if($modalMotivoAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h2 class="modal-title fs-6">Cambio en plan firmado</h2>
                <button wire:click="cancelarCambio" type="button" class="btn-close" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <p class="small text-secondary mb-0">
                    Para realizar este cambio en un plan activo, indica el motivo.
                    Quedará registrado en el historial del plan.
                </p>
                <textarea
                    wire:model.live="motivoTexto"
                    class="form-control form-control-sm"
                    rows="3"
                    placeholder="ej: se actualizó la ficha de vivienda tras visita domiciliaria…"
                    autofocus
                ></textarea>
            </div>
            <div class="modal-footer">
                <button wire:click="cancelarCambio" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button
                    wire:click="confirmarCambioConMotivo"
                    class="btn btn-primary btn-sm"
                    @if(empty(trim($motivoTexto))) disabled @endif
                >
                    <x-heroicon-o-check class="icon-14"/>
                    Confirmar cambio
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: NUEVO OBJETIVO GENERAL
     ============================================================ --}}
@if($modalObjetivoAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Añadir objetivo</h5>
                <button type="button" class="btn-close" wire:click="$set('modalObjetivoAbierto', false)"></button>
            </div>

            {{-- Selector de modo --}}
            <div class="modal-body pb-0">
                <div class="d-flex gap-2 mb-3">
                    <button wire:click="$set('modoObjetivo', 'catalogo')"
                            class="btn btn-sm {{ $modoObjetivo === 'catalogo' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <x-heroicon-o-list-bullet class="icon-14"/>
                        Del catálogo
                    </button>
                    <button wire:click="$set('modoObjetivo', 'libre')"
                            class="btn btn-sm {{ $modoObjetivo === 'libre' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <x-heroicon-o-pencil class="icon-14"/>
                        Objetivo libre
                    </button>
                </div>
            </div>

            {{-- Modo catálogo --}}
            @if($modoObjetivo === 'catalogo')
            <div class="modal-body pt-0">
                @if(! $this->plan?->tipo_plan_id)
                    {{-- El plan no tiene tipo asignado: mostrar selector inline --}}
                    <div class="alert alert-warning mb-0">
                        <p class="mb-2 small fw-semibold">El plan no tiene un tipo de plan asignado.</p>
                        <p class="mb-3 small text-body-secondary">Selecciona el tipo de plan para acceder al catálogo de objetivos.</p>
                        <select wire:model.live="tipoPlanId" class="form-select form-select-sm mb-2">
                            <option value="">— Elige un tipo de plan —</option>
                            @foreach($this->tiposPlanes as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @if($tipoPlanId)
                        <button wire:click="asignarTipoPlan" class="btn btn-sm btn-warning">
                            <x-heroicon-o-check class="icon-14"/>
                            Asignar tipo y ver objetivos
                        </button>
                        @endif
                    </div>
                @elseif($this->objetivosCatalogo->isEmpty())
                    <p class="text-body-secondary fst-italic mb-0">
                        No hay objetivos activos configurados en el catálogo para este tipo de plan.
                        Usa "Objetivo libre" para redactar uno manualmente, o configura los objetivos del catálogo en Configuración.
                    </p>
                @else
                    @php $yaEnPlan = $this->catalogoIdsEnPlan; @endphp

                    {{-- Objetivos generales --}}
                    <p class="text-body-secondary small mb-2 fw-semibold">Objetivos generales</p>
                    <div class="d-flex flex-column gap-2 mb-4">
                        @foreach($this->objetivosCatalogo as $oc)
                        @php $yaAñadido = in_array((int)$oc->id, $yaEnPlan, true); @endphp
                        <div class="border rounded p-3 {{ $yaAñadido ? 'opacity-50' : '' }}">
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="oc-{{ $oc->id }}"
                                       wire:model="objetivosCatalogoSeleccionados"
                                       value="{{ $oc->id }}"
                                       {{ $yaAñadido ? 'disabled' : '' }}>
                                <label class="form-check-label fw-semibold" for="oc-{{ $oc->id }}">
                                    {{ $oc->texto }}
                                    @if($yaAñadido)
                                    <span class="badge bg-secondary ms-1 fw-normal">ya añadido</span>
                                    @endif
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Objetivos específicos (solo si hay fichas con área temática coincidente) --}}
                    @if($this->objetivosEspecificosCatalogo->isNotEmpty())
                    <p class="text-body-secondary small mb-2 fw-semibold">Objetivos específicos</p>
                    <p class="text-body-secondary small mb-2">Derivados de las fichas incluidas en el diagnóstico:</p>
                    <div class="d-flex flex-column gap-2">
                        @foreach($this->objetivosEspecificosCatalogo as $esp)
                        @php $espYaAñadido = in_array((int)$esp->id, $yaEnPlan, true); @endphp
                        <div class="border rounded p-3 {{ $espYaAñadido ? 'opacity-50' : '' }}">
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="esp-{{ $esp->id }}"
                                       wire:model="objetivosCatalogoSeleccionados"
                                       value="{{ $esp->id }}"
                                       {{ $espYaAñadido ? 'disabled' : '' }}>
                                <label class="form-check-label" for="esp-{{ $esp->id }}">
                                    {{ $esp->texto }}
                                    @if($esp->tipoFicha)
                                    <span class="badge bg-primary-subtle text-primary-emphasis font-monospace fw-normal ms-1">{{ $esp->tipoFicha->nombre }}</span>
                                    @endif
                                    @if($espYaAñadido)
                                    <span class="badge bg-secondary ms-1 fw-normal">ya añadido</span>
                                    @endif
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                @endif
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalObjetivoAbierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="guardarObjetivosDesdeCatalogo"
                        class="btn btn-primary btn-sm"
                        x-bind:disabled="$wire.objetivosCatalogoSeleccionados.length === 0">
                    <x-heroicon-o-check class="icon-14"/>
                    Añadir seleccionados
                </button>
            </div>

            {{-- Modo libre --}}
            @else
            <div class="modal-body pt-0">
                <label class="form-label small">Descripción del objetivo</label>
                <textarea wire:model="nuevoObjetivoTexto"
                          class="form-control form-control-sm @error('nuevoObjetivoTexto') is-invalid @enderror"
                          rows="3"
                          placeholder="Ej: Mejorar la autonomía económica de la unidad familiar…"
                          autofocus></textarea>
                @error('nuevoObjetivoTexto')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalObjetivoAbierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="guardarObjetivo" class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar objetivo
                </button>
            </div>
            @endif

        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: NUEVA ACTUACIÓN DEL AYUNTAMIENTO
     ============================================================ --}}
@if($modalActuacionAytoAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Nueva actuación del Ayuntamiento</h5>
                <button type="button" class="btn-close" wire:click="$set('modalActuacionAytoAbierto', false)"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <div>
                    <label class="form-label small">Prestación <span class="text-danger">*</span></label>
                    <select wire:model="nuevaActuacionPrestacionId"
                            class="form-select form-select-sm @error('nuevaActuacionPrestacionId') is-invalid @enderror">
                        <option value="">— Selecciona una prestación —</option>
                        @foreach($this->prestacionesCatalogo as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}
                            @if($p->codigo) ({{ $p->codigo }}) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('nuevaActuacionPrestacionId')
                    <div class="invalid-feedback">Selecciona una prestación.</div>
                    @enderror
                </div>
                <div>
                    <label class="form-label small">Concreción específica <span class="text-secondary fw-normal">(opcional)</span></label>
                    <textarea wire:model="nuevaActuacionDescripcion"
                              class="form-control form-control-sm"
                              rows="2"
                              placeholder="Detalle concreto de la actuación…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalActuacionAytoAbierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="guardarActuacionAyto" class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar actuación
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: NUEVO COMPROMISO DEL CIUDADANO
     ============================================================ --}}
@if($modalCompromisoAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo compromiso de la persona</h5>
                <button type="button" class="btn-close" wire:click="$set('modalCompromisoAbierto', false)"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Descripción del compromiso</label>
                <textarea wire:model="nuevoCompromisoDescripcion"
                          class="form-control form-control-sm @error('nuevoCompromisoDescripcion') is-invalid @enderror"
                          rows="3"
                          placeholder="Ej: Asistir al taller de inserción laboral los martes…"
                          autofocus></textarea>
                @error('nuevoCompromisoDescripcion')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalCompromisoAbierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="guardarCompromisoCiudadano" class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar compromiso
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: NUEVO PARTICIPANTE PROFESIONAL
     ============================================================ --}}
@if($modalParticipanteAbierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Añadir profesional participante</h5>
                <button type="button" class="btn-close" wire:click="$set('modalParticipanteAbierto', false)"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <div>
                    <label class="form-label small">Profesional <span class="text-danger">*</span></label>
                    <select wire:model="nuevoParticipanteUserId"
                            class="form-select form-select-sm @error('nuevoParticipanteUserId') is-invalid @enderror">
                        <option value="">— Selecciona un profesional —</option>
                        @foreach($this->usuariosProfesionales as $u)
                        <option value="{{ $u->id }}">{{ $u->nombre_completo }}</option>
                        @endforeach
                    </select>
                    @error('nuevoParticipanteUserId')
                    <div class="invalid-feedback">Selecciona un profesional.</div>
                    @enderror
                </div>
                <div>
                    <label class="form-label small">Rol en el plan <span class="text-danger">*</span></label>
                    <input wire:model="nuevoParticipanteRol"
                           type="text"
                           class="form-control form-control-sm @error('nuevoParticipanteRol') is-invalid @enderror"
                           placeholder="Ej: Educador social de apoyo, Psicólogo…">
                    @error('nuevoParticipanteRol')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalParticipanteAbierto', false)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="guardarParticipante" class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Añadir participante
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: CIERRE DEL PLAN
     ============================================================ --}}
@if($modalCierreAbierto)
<div
    class="modal fade show d-block"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalCierre()"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Cerrar plan de intervención</h5>
                <button type="button" class="btn-close" wire:click="cerrarModalCierre" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <p class="small text-secondary mb-0">
                    Una vez cerrado, el plan queda en modo solo lectura.
                    Esta acción queda registrada en el historial.
                </p>

                <div>
                    <label class="form-label small">Motivo de cierre <span class="text-danger">*</span></label>
                    <select wire:model.live="motivoCierre" class="form-select form-select-sm">
                        <option value="">— Selecciona un motivo —</option>
                        @foreach($this->motivosCierre as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small">Observaciones <span class="text-secondary fw-normal">(opcional)</span></label>
                    <textarea
                        wire:model="notasCierre"
                        class="form-control form-control-sm"
                        rows="2"
                        placeholder="Se añadirán como apunte en la historia social si se rellenan…"
                    ></textarea>
                </div>

                @if(in_array($motivoCierre, ['negativa_firma', 'imposibilidad_localizacion']))
                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-0">
                    <x-heroicon-o-exclamation-triangle class="icon-14 flex-shrink-0 mt-1"/>
                    <span class="small">Este motivo de cierre requiere dejar constancia en el historial de apuntes.
                    Usa el campo de observaciones para documentarlo.</span>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button wire:click="cerrarModalCierre" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button
                    wire:click="confirmarCierrePlan"
                    class="btn btn-danger btn-sm"
                    @if(empty($motivoCierre)) disabled @endif
                >
                    <x-heroicon-o-x-circle class="icon-14"/>
                    Confirmar cierre
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- Modal: Editar objetivo --}}
@if($modalEditarObjetivoAbierto)
<div class="modal fade show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Editar objetivo</h5>
                <button type="button" class="btn-close"
                        wire:click="$set('modalEditarObjetivoAbierto', false)"
                        aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Texto del objetivo</label>
                <textarea wire:model="editarObjetivoTexto"
                          class="form-control form-control-sm @error('editarObjetivoTexto') is-invalid @enderror"
                          rows="3"></textarea>
                @error('editarObjetivoTexto')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalEditarObjetivoAbierto', false)"
                        class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="eliminarObjetivo"
                        class="btn btn-outline-danger btn-sm">
                    <x-heroicon-o-trash class="icon-14"/>
                    Eliminar
                </button>
                <button wire:click="guardarEdicionObjetivo"
                        class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif


{{-- ============================================================
     MODAL: EDITAR COMPROMISO DEL CIUDADANO
     ============================================================ --}}
@if($modalEditarCompromisoCiudadanoAbierto)
<div class="modal fade show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Editar compromiso</h5>
                <button type="button" class="btn-close"
                        wire:click="$set('modalEditarCompromisoCiudadanoAbierto', false)"
                        aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Descripción del compromiso</label>
                <textarea wire:model="editarCompromisoCiudadanoDescripcion"
                          class="form-control form-control-sm @error('editarCompromisoCiudadanoDescripcion') is-invalid @enderror"
                          rows="3"></textarea>
                @error('editarCompromisoCiudadanoDescripcion')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalEditarCompromisoCiudadanoAbierto', false)"
                        class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="eliminarCompromisoCiudadano"
                        class="btn btn-outline-danger btn-sm">
                    <x-heroicon-o-trash class="icon-14"/>
                    Eliminar
                </button>
                <button wire:click="guardarEdicionCompromisoCiudadano"
                        class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

{{-- ============================================================
     MODAL: EDITAR ACTUACIÓN DEL AYUNTAMIENTO
     ============================================================ --}}
@if($modalEditarActuacionAytoAbierto)
<div class="modal fade show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Editar actuación del Ayuntamiento</h5>
                <button type="button" class="btn-close"
                        wire:click="$set('modalEditarActuacionAytoAbierto', false)"
                        aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <div>
                    <label class="form-label small">Prestación <span class="text-danger">*</span></label>
                    <select wire:model="editarActuacionAytoPrestacionId"
                            class="form-select form-select-sm @error('editarActuacionAytoPrestacionId') is-invalid @enderror">
                        <option value="">— Selecciona una prestación —</option>
                        @foreach($this->prestacionesCatalogo as $p)
                        <option value="{{ $p->id }}">{{ $p->nombre }}
                            @if($p->codigo) ({{ $p->codigo }}) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('editarActuacionAytoPrestacionId')
                    <div class="invalid-feedback">Selecciona una prestación.</div>
                    @enderror
                </div>
                <div>
                    <label class="form-label small">Concreción específica <span class="text-secondary fw-normal">(opcional)</span></label>
                    <textarea wire:model="editarActuacionAytoDescripcion"
                              class="form-control form-control-sm"
                              rows="2"
                              placeholder="Detalle concreto de la actuación…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button wire:click="$set('modalEditarActuacionAytoAbierto', false)"
                        class="btn btn-outline-secondary btn-sm">Cancelar</button>
                <button wire:click="eliminarActuacionAyto"
                        class="btn btn-outline-danger btn-sm">
                    <x-heroicon-o-trash class="icon-14"/>
                    Eliminar
                </button>
                <button wire:click="guardarEdicionActuacionAyto"
                        class="btn btn-primary btn-sm">
                    <x-heroicon-o-check class="icon-14"/>
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

</div>{{-- fin plan-layout --}}
