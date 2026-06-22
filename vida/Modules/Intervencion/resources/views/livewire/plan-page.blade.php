<div
    class="plan-layout"
    x-data
    x-on:keydown.escape.window="
        $wire.drawerAbierto && $wire.cerrarDrawer();
        $wire.modalMotivoAbierto && $wire.cancelarCambio();
    "
>

{{-- ============================================================
     BANDA DE CONTEXTO (sticky)
     ============================================================ --}}
<div class="plan-topbar">
    <a href="{{ route('intervencion.ciudadano.show', $this->plan?->historia_id ?? $this->historiaId) }}"
       wire:navigate
       class="plan-topbar__back">
        <x-heroicon-o-arrow-left class="icon-13"/>
        Intervención
    </a>

    <div class="plan-topbar__citizen">
        <span class="plan-topbar__name">
            {{ $this->ciudadano?->nombre_completo ?? '—' }}
        </span>
        <span class="plan-topbar__meta">
            {{ $this->plan?->tipoPlan?->nombre ?? 'Plan de intervención' }}
        </span>
    </div>

    <div class="plan-topbar__badges">
        @if($this->plan)
        <span class="plan-badge plan-badge--{{ $this->plan->estado->value }}">
            {{ $this->plan->estado->label() }}
        </span>
        <span class="plan-badge plan-badge--version">v{{ $this->plan->version }}</span>
        @endif
    </div>

    <div class="plan-topbar__actions">
        @if($this->plan)
        <button wire:click="generarPdf" class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-arrow-down-tray class="icon-13"/>
            Generar PDF
        </button>
        @endif

        @if($this->plan?->estado->value === 'borrador')
        <button
            wire:click="activarPlan"
            class="btn btn-primary btn-sm"
            @if(! $this->puedeActivarse) disabled title="Marca ambas firmas para activar" @endif
        >
            <x-heroicon-o-check class="icon-13"/>
            Activar plan
        </button>
        @endif

        @if($this->plan?->estado->value === 'activo')
        <button class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-x-circle class="icon-13"/>
            Cerrar plan
        </button>
        @endif
    </div>
</div>

{{-- Mensaje de éxito --}}
@if($mensajeExito)
<div class="plan-exito" x-init="setTimeout(() => $wire.set('mensajeExito', ''), 3000)">
    <x-heroicon-o-check-circle class="icon-13"/>
    {{ $mensajeExito }}
</div>
@endif

{{-- ============================================================
     CUERPO + ÍNDICE
     ============================================================ --}}
<div class="plan-body-wrap">
<div class="plan-body">

    {{-- SECCIÓN 0: Datos de la persona --}}
    <div class="plan-section" id="ps-datos">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-user class="icon-15"/>
                Datos de la persona
            </div>
            <span class="plan-readonly-badge">Solo lectura · Historia Social</span>
        </div>
        <div class="plan-section__body">
            <div class="plan-citizen-grid">
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Nombre completo</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->nombre_completo }}</div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Fecha de nacimiento</div>
                    <div class="plan-citizen-value">
                        {{ $this->ciudadano?->fecha_nacimiento ? \Carbon\Carbon::parse($this->ciudadano->fecha_nacimiento)->format('d/m/Y') : '—' }}
                    </div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Documento</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->documento_identidad ?? '—' }}</div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Domicilio</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->domicilio }}</div>
                </div>
            </div>

            @if($this->miembrosUc->isNotEmpty())
            <div class="plan-uc-members">
                <div class="plan-uc-label">Unidad de convivencia</div>
                @foreach($this->miembrosUc as $m)
                <span class="plan-member-pill">
                    {{ $m['ciudadano']->nombre_completo }}
                    @if($m['relacion'])
                    <span class="plan-member-relacion">{{ $m['relacion'] }}</span>
                    @endif
                </span>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 1: Diagnóstico social --}}
    <div class="plan-section" id="ps-diagnostico">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-document-text class="icon-15"/>
                Diagnóstico social
            </div>
            <button wire:click="abrirDrawer" class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-circle-stack class="icon-13"/>
                Añadir fichas
            </button>
        </div>
        <div class="plan-section__body">

            {{-- Bloque A: Evidencia de fichas --}}
            <div class="plan-evidencia">
                @forelse($this->fichasDiagnostico as $pfd)
                <div class="plan-ficha-card" wire:key="pfd-{{ $pfd->id }}"
                     x-data="{ expandida: false }">
                    <div class="plan-ficha-header">
                        <div class="plan-ficha-title plan-ficha-title--toggle" @click="expandida = !expandida" :aria-expanded="expandida">
                            <x-heroicon-o-lock-closed class="icon-12 plan-icon-muted"/>
                            {{ $pfd->ficha?->tipoFicha?->nombre ?? 'Ficha' }}
                            <span class="plan-ficha-date">
                                {{ $pfd->ficha?->created_at?->format('d/m/Y') }}
                            </span>
                            <x-heroicon-o-chevron-down class="icon-12 op-toggle-icon"/>
                        </div>
                        <button
                            wire:click="eliminarFichaDiagnostico({{ $pfd->ficha_id }})"
                            class="btn btn-outline-danger btn-sm p-1 lh-1"
                            title="Eliminar del diagnóstico"
                        >
                            <x-heroicon-o-x-mark class="icon-12"/>
                        </button>
                    </div>
                    <div class="plan-ficha-content" x-show="expandida" x-cloak>
                        @php $datos = $pfd->ficha?->datos ?? [] @endphp
                        @forelse($datos as $campo => $valor)
                        <div class="plan-ficha-campo">
                            <span class="plan-ficha-campo-label">{{ $campo }}</span>
                            <span class="plan-ficha-campo-valor">{{ is_array($valor) ? implode(', ', $valor) : $valor }}</span>
                        </div>
                        @empty
                        <span class="plan-ficha-vacia">Sin datos registrados.</span>
                        @endforelse
                    </div>
                </div>
                @empty
                <div class="plan-evidencia-vacia">
                    Ninguna ficha añadida aún.
                    <button wire:click="abrirDrawer" class="btn btn-link btn-sm p-0 align-baseline">Añadir fichas del historial</button>
                </div>
                @endforelse

                @if($this->fichasDiagnostico->isNotEmpty())
                <button wire:click="abrirDrawer" class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                    <x-heroicon-o-plus class="icon-13"/>
                    Añadir otra ficha
                </button>
                @endif
            </div>

            {{-- Bloque B: Síntesis profesional --}}
            <div class="plan-sintesis">
                <div class="plan-sintesis-label">
                    <x-heroicon-o-pencil class="icon-13"/>
                    Síntesis profesional
                </div>
                <div class="plan-editor-toolbar">
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('bold')"
                            title="Negrita"><strong>B</strong></button>
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('italic')"
                            title="Cursiva"><em>I</em></button>
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1" onclick="document.execCommand('insertUnorderedList')"
                            title="Lista">
                        <x-heroicon-o-list-bullet class="icon-13"/>
                    </button>
                </div>
                <div
                    class="plan-editor-area"
                    contenteditable="{{ $this->plan?->estado !== 'cerrado' ? 'true' : 'false' }}"
                    x-data
                    x-on:blur="$wire.set('diagnosticoTexto', $el.innerHTML); $wire.guardarDiagnostico()"
                >{{ $diagnosticoTexto }}</div>
            </div>

        </div>
    </div>

    {{-- SECCIÓN 2: Objetivos --}}
    <div class="plan-section" id="ps-objetivos">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-viewfinder-circle class="icon-15"/>
                Objetivos
            </div>
            <button class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-13"/>
                Añadir objetivo
            </button>
        </div>
        <div class="plan-section__body">
            @if($this->objetivosGenerales->isEmpty())
            <div class="plan-vacio">Ningún objetivo definido aún.</div>
            @else
            <div class="plan-obj-grid">
                @foreach($this->objetivosGenerales as $og)
                <div class="plan-obj-general" wire:key="og-{{ $og->id }}">
                    <div class="plan-obj-texto">{{ $og->texto }}</div>
                    @if($og->objetivosEspecificos->isNotEmpty())
                    <ul class="plan-obj-especificos">
                        @foreach($og->objetivosEspecificos as $oe)
                        <li wire:key="oe-{{ $oe->id }}">{{ $oe->texto }}</li>
                        @endforeach
                    </ul>
                    @endif
                    <div class="plan-obj-footer">
                        <span class="plan-estado-badge plan-estado-{{ $og->estado }}">
                            {{ ucfirst(str_replace('_', ' ', $og->estado)) }}
                        </span>
                        <button class="btn btn-outline-secondary btn-sm p-1 lh-1">
                            <x-heroicon-o-pencil-square class="icon-13"/>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 3: Compromisos del Ayuntamiento --}}
    <div class="plan-section" id="ps-ayto">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-building-office class="icon-15"/>
                Compromisos del Ayuntamiento
            </div>
            <button class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-13"/>
                Añadir
            </button>
        </div>
        <div class="plan-section__body plan-section__body--no-pad">
            @if($this->actuacionesAyuntamiento->isEmpty())
            <div class="plan-vacio plan-vacio--padded">Ninguna actuación definida.</div>
            @else
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 plan-table">
                <thead>
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
                            <div class="plan-prestacion-name">{{ $act->prestacion->nombre }}</div>
                            <div class="plan-prestacion-code">{{ $act->prestacion->codigo }}</div>
                        </td>
                        <td class="plan-td-secondary">{{ $act->descripcion_especifica ?? '—' }}</td>
                        <td>
                            @if($act->responsable)
                            <div class="plan-avatar-sm">{{ substr($act->responsable->name, 0, 2) }}</div>
                            @else —
                            @endif
                        </td>
                        <td class="plan-td-secondary">{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="plan-estado-badge plan-estado-{{ $act->estado }}">{{ ucfirst($act->estado) }}</span></td>
                        <td><button class="btn btn-outline-secondary btn-sm p-1 lh-1"><x-heroicon-o-pencil-square class="icon-13"/></button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table></div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 4: Compromisos del ciudadano --}}
    <div class="plan-section" id="ps-ciudadano">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-check-badge class="icon-15"/>
                Compromisos de la persona
            </div>
            <button class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-13"/>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            @if($this->actuacionesCiudadano->isEmpty())
            <div class="plan-vacio">Ningún compromiso definido.</div>
            @else
            <div class="list-group list-group-flush plan-comp-list">
                @foreach($this->actuacionesCiudadano as $act)
                <div class="list-group-item plan-comp-item" wire:key="aciu-{{ $act->id }}">
                    <x-heroicon-o-check-circle class="icon-14"/>
                    <div>
                        <div>{{ $act->descripcion }}</div>
                        @if($act->prestacion)
                        <span class="plan-prestacion-pill">{{ $act->prestacion->nombre }}</span>
                        @endif
                    </div>
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1 ms-auto">
                        <x-heroicon-o-pencil-square class="icon-13"/>
                    </button>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 5: Participantes --}}
    <div class="plan-section" id="ps-participantes">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-users class="icon-15"/>
                Profesionales participantes
            </div>
            <button class="btn btn-outline-secondary btn-sm">
                <x-heroicon-o-plus class="icon-13"/>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            <div class="list-group list-group-flush plan-part-list">
                @foreach($this->participantes as $p)
                <div class="list-group-item plan-part-row" wire:key="part-{{ $p->id }}">
                    <div class="plan-part-avatar">{{ substr($p->profesional->name, 0, 2) }}</div>
                    <div class="plan-part-info">
                        <div class="plan-part-name">{{ $p->profesional->name }}</div>
                        <div class="plan-part-rol">
                            {{ $p->rol_en_plan }}
                            @if($p->servicio) · {{ $p->servicio->nombre }} @endif
                        </div>
                    </div>
                    @if($p->user_id === $this->plan?->profesional_responsable_id)
                    <span class="plan-badge-responsable">Responsable</span>
                    @else
                    <button class="btn btn-outline-secondary btn-sm p-1 lh-1">
                        <x-heroicon-o-x-mark class="icon-13"/>
                    </button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- SECCIÓN 6: Seguimiento y firmas --}}
    <div class="plan-section" id="ps-firmas">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <x-heroicon-o-pencil class="icon-15"/>
                Seguimiento y firmas
            </div>
        </div>
        <div class="plan-section__body">

            {{-- Condiciones de seguimiento --}}
            <div class="plan-seguimiento">
                <div class="plan-seguimiento-title">Condiciones de seguimiento</div>
                <div class="plan-seguimiento-fields">
                    <div class="plan-field">
                        <label class="form-label plan-label">Frecuencia de seguimiento</label>
                        <select
                            wire:model.live="periodicidadSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="form-select form-select-sm plan-select"
                        >
                            <option value="bimensual">Bimensual</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="cuatrimestral">Cuatrimestral</option>
                            <option value="semestral">Semestral</option>
                        </select>
                    </div>
                    <div class="plan-field plan-field--full">
                        <label class="form-label plan-label">Observaciones sobre el seguimiento</label>
                        <textarea
                            wire:model.lazy="observacionesSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="form-control form-control-sm plan-textarea"
                            rows="2"
                            placeholder="Acuerdos sobre el seguimiento, condiciones especiales…"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="plan-firmas-divider"></div>

            {{-- Firmas --}}
            <div class="plan-firmas-grid">
                <div class="plan-firma-card">
                    <div class="plan-firma-quien">{{ $this->plan?->profesionalResponsable?->name }}</div>
                    <div class="plan-firma-rol">Profesional responsable</div>
                    <label class="plan-firma-check">
                        <input
                            type="checkbox"
                            wire:model.live="profesionalFirmado"
                            wire:change="marcarFirmaProfesional($event.target.checked)"
                            @if($this->plan?->estado->value === 'cerrado') disabled @endif
                        >
                        Ha firmado en papel
                    </label>
                    @if($profesionalFirmado)
                    <div class="plan-firma-fecha-reg">
                        Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('profesional_firmado_en')?->format('d/m/Y H:i') }}
                    </div>
                    @endif
                </div>

                <div class="plan-firma-card">
                    <div class="plan-firma-quien">{{ $this->ciudadano?->nombre_completo }}</div>
                    <div class="plan-firma-rol">Persona interesada</div>
                    <label class="plan-firma-check">
                        <input
                            type="checkbox"
                            wire:model.live="ciudadanoFirmado"
                            wire:change="marcarFirmaCiudadano($event.target.checked)"
                            @if($this->plan?->estado->value === 'cerrado') disabled @endif
                        >
                        Ha firmado en papel
                    </label>
                    @if($ciudadanoFirmado)
                    <div class="plan-firma-fecha-reg">
                        Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('ciudadano_firmado_en')?->format('d/m/Y H:i') }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Fecha de firma presencial --}}
            <div class="plan-field plan-field--compact">
                <label class="form-label plan-label">Fecha de la firma presencial</label>
                <input
                    type="date"
                    wire:model.lazy="fechaFirmaPresencial"
                    wire:change="guardarFechaFirma"
                    class="form-control form-control-sm plan-input"
                >
            </div>

            @if($this->puedeActivarse)
            <div class="plan-firma-lista-ok">
                <x-heroicon-o-check-circle class="icon-14"/>
                Ambas partes han firmado. El plan puede activarse desde el botón superior.
            </div>
            @endif

            <div class="plan-firma-nota">
                Una vez activado el plan, cualquier cambio requerirá indicar el motivo.
                El PDF puede generarse en cualquier momento desde el botón superior.
            </div>

        </div>
    </div>

</div>{{-- fin plan-body --}}

{{-- ÍNDICE LATERAL --}}
<nav class="plan-index" aria-label="Secciones del plan">
    <div class="plan-index-label">Secciones</div>
    <a href="#ps-datos"         class="plan-index-item"><span class="plan-index-dot plan-index-dot--done"></span> Datos</a>
    <a href="#ps-diagnostico"   class="plan-index-item"><span class="plan-index-dot plan-index-dot--current"></span> Diagnóstico</a>
    <a href="#ps-objetivos"     class="plan-index-item"><span class="plan-index-dot"></span> Objetivos</a>
    <a href="#ps-ayto"          class="plan-index-item"><span class="plan-index-dot"></span> Ayuntamiento</a>
    <a href="#ps-ciudadano"     class="plan-index-item"><span class="plan-index-dot"></span> Ciudadano</a>
    <a href="#ps-participantes" class="plan-index-item"><span class="plan-index-dot"></span> Participantes</a>
    <a href="#ps-firmas"        class="plan-index-item"><span class="plan-index-dot"></span> Firmas</a>

    <div class="plan-index-meta">
        <div class="plan-index-meta-label">Seguimiento</div>
        <div class="plan-index-meta-value">{{ ucfirst($periodicidadSeguimiento) }}</div>
    </div>
</nav>
</div>{{-- fin plan-body-wrap --}}

{{-- ============================================================
     DRAWER DEL HISTORIAL
     ============================================================ --}}
@if($drawerAbierto)
<div class="modal-backdrop fade show"></div>
<div class="offcanvas offcanvas-end show d-block border-start shadow"
     tabindex="-1"
     style="--bs-offcanvas-width: 380px;"
     wire:click.self="cerrarDrawer"
     x-data="{ seleccion: @entangle('fichasSeleccionadas') }">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title fs-6">Historia social - fichas</h2>
        <button wire:click="cerrarDrawer" type="button" class="btn-close" aria-label="Cerrar"></button>
    </div>

    <div class="plan-drawer-filters">
        <button wire:click="$set('drawerFiltroFecha','todas')"
            class="plan-chip {{ $drawerFiltroFecha === 'todas' ? 'plan-chip--on' : '' }}">Todas</button>
        <button wire:click="$set('drawerFiltroFecha','mes')"
            class="plan-chip {{ $drawerFiltroFecha === 'mes' ? 'plan-chip--on' : '' }}">Último mes</button>
        <button wire:click="$set('drawerFiltroFecha','anio')"
            class="plan-chip {{ $drawerFiltroFecha === 'anio' ? 'plan-chip--on' : '' }}">Último año</button>
    </div>

    <div class="plan-drawer-body">
        @forelse($this->valoracionesTimeline as $val)
        <div class="plan-drawer-val" wire:key="val-{{ $val->id }}">
            <div class="plan-drawer-val-header">
                {{ $val->tipoValoracion?->nombre ?? 'Valoración' }}
                <span class="plan-drawer-val-date">{{ $val->created_at->format('d/m/Y') }}</span>
            </div>
            @foreach($val->fichas as $ficha)
            <div class="plan-drawer-ficha" wire:key="df-{{ $ficha->id }}">
                <input
                    type="checkbox"
                    id="df{{ $ficha->id }}"
                    value="{{ $ficha->id }}"
                    x-model="seleccion"
                >
                <label for="df{{ $ficha->id }}">{{ $ficha->tipoFicha?->nombre ?? 'Ficha' }}</label>
                @if(in_array($ficha->id, $fichasSeleccionadas))
                <span class="plan-chip plan-chip--on plan-chip--compact">Añadida</span>
                @endif
            </div>
            @endforeach
        </div>
        @empty
        <div class="plan-vacio plan-vacio--padded">No hay valoraciones en el historial.</div>
        @endforelse
    </div>

    <div class="border-top d-flex gap-2 justify-content-end px-3 py-3">
        <button wire:click="cerrarDrawer" class="btn btn-outline-secondary btn-sm">Cancelar</button>
        <button
            x-on:click="$wire.aplicarSeleccionFichas(seleccion)"
            class="btn btn-primary btn-sm"
        >
            <x-heroicon-o-check class="icon-13"/>
            Aplicar selección
        </button>
    </div>
</div>
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
                    wire:model="motivoTexto"
                    class="form-control form-control-sm plan-textarea"
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
                    <x-heroicon-o-check class="icon-13"/>
                    Confirmar cambio
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

</div>{{-- fin plan-layout --}}
