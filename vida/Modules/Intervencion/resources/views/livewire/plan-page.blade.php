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
        <i data-lucide="arrow-left" class="icon-13"></i>
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
        <button wire:click="generarPdf" class="plan-btn">
            <i data-lucide="file-down" class="icon-13"></i>
            Generar PDF
        </button>
        @endif

        @if($this->plan?->estado->value === 'borrador')
        <button
            wire:click="activarPlan"
            class="plan-btn plan-btn--primary"
            @if(! $this->puedeActivarse) disabled title="Marca ambas firmas para activar" @endif
        >
            <i data-lucide="check" class="icon-13"></i>
            Activar plan
        </button>
        @endif

        @if($this->plan?->estado->value === 'activo')
        <button class="plan-btn">
            <i data-lucide="x-circle" class="icon-13"></i>
            Cerrar plan
        </button>
        @endif
    </div>
</div>

{{-- Mensaje de éxito --}}
@if($mensajeExito)
<div class="plan-exito" x-init="setTimeout(() => $wire.set('mensajeExito', ''), 3000)">
    <i data-lucide="check-circle" class="icon-13"></i>
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
                <i data-lucide="user" class="icon-15"></i>
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
                <i data-lucide="file-text" class="icon-15"></i>
                Diagnóstico social
            </div>
            <button wire:click="abrirDrawer" class="plan-btn">
                <i data-lucide="database" class="icon-13"></i>
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
                        <div class="plan-ficha-title plan-ficha-title--toggle" @click="expandida = !expandida">
                            <i data-lucide="lock" class="icon-12 plan-icon-muted"></i>
                            {{ $pfd->ficha?->tipoFicha?->nombre ?? 'Ficha' }}
                            <span class="plan-ficha-date">
                                {{ $pfd->ficha?->created_at?->format('d/m/Y') }}
                            </span>
                            <i data-lucide="chevron-down" class="icon-12"
                               x-bind:class="expandida ? 'plan-icon-rotate-180' : ''"></i>
                        </div>
                        <button
                            wire:click="eliminarFichaDiagnostico({{ $pfd->ficha_id }})"
                            class="plan-ficha-remove"
                            title="Eliminar del diagnóstico"
                        >
                            <i data-lucide="x" class="icon-12"></i>
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
                    <button wire:click="abrirDrawer" class="plan-link">Añadir fichas del historial</button>
                </div>
                @endforelse

                @if($this->fichasDiagnostico->isNotEmpty())
                <button wire:click="abrirDrawer" class="plan-add-ficha-btn">
                    <i data-lucide="plus" class="icon-13"></i>
                    Añadir otra ficha
                </button>
                @endif
            </div>

            {{-- Bloque B: Síntesis profesional --}}
            <div class="plan-sintesis">
                <div class="plan-sintesis-label">
                    <i data-lucide="pencil" class="icon-13"></i>
                    Síntesis profesional
                </div>
                <div class="plan-editor-toolbar">
                    <button class="plan-tb-btn" onclick="document.execCommand('bold')"
                            title="Negrita"><strong>B</strong></button>
                    <button class="plan-tb-btn" onclick="document.execCommand('italic')"
                            title="Cursiva"><em>I</em></button>
                    <button class="plan-tb-btn" onclick="document.execCommand('insertUnorderedList')"
                            title="Lista">
                        <i data-lucide="list" class="icon-13"></i>
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
                <i data-lucide="target" class="icon-15"></i>
                Objetivos
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" class="icon-13"></i>
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
                        <button class="plan-tb-btn">
                            <i data-lucide="edit" class="icon-13"></i>
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
                <i data-lucide="building" class="icon-15"></i>
                Compromisos del Ayuntamiento
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" class="icon-13"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body plan-section__body--no-pad">
            @if($this->actuacionesAyuntamiento->isEmpty())
            <div class="plan-vacio plan-vacio--padded">Ninguna actuación definida.</div>
            @else
            <table class="plan-table">
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
                        <td><button class="plan-tb-btn"><i data-lucide="edit" class="icon-13"></i></button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 4: Compromisos del ciudadano --}}
    <div class="plan-section" id="ps-ciudadano">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="user-check" class="icon-15"></i>
                Compromisos de la persona
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" class="icon-13"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            @if($this->actuacionesCiudadano->isEmpty())
            <div class="plan-vacio">Ningún compromiso definido.</div>
            @else
            <div class="plan-comp-list">
                @foreach($this->actuacionesCiudadano as $act)
                <div class="plan-comp-item" wire:key="aciu-{{ $act->id }}">
                    <i data-lucide="circle-check" class="icon-14"></i>
                    <div>
                        <div>{{ $act->descripcion }}</div>
                        @if($act->prestacion)
                        <span class="plan-prestacion-pill">{{ $act->prestacion->nombre }}</span>
                        @endif
                    </div>
                    <button class="plan-tb-btn plan-tb-btn--push">
                        <i data-lucide="edit" class="icon-13"></i>
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
                <i data-lucide="users" class="icon-15"></i>
                Profesionales participantes
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" class="icon-13"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            <div class="plan-part-list">
                @foreach($this->participantes as $p)
                <div class="plan-part-row" wire:key="part-{{ $p->id }}">
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
                    <button class="plan-tb-btn">
                        <i data-lucide="x" class="icon-13"></i>
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
                <i data-lucide="pen-line" class="icon-15"></i>
                Seguimiento y firmas
            </div>
        </div>
        <div class="plan-section__body">

            {{-- Condiciones de seguimiento --}}
            <div class="plan-seguimiento">
                <div class="plan-seguimiento-title">Condiciones de seguimiento</div>
                <div class="plan-seguimiento-fields">
                    <div class="plan-field">
                        <label class="plan-label">Frecuencia de seguimiento</label>
                        <select
                            wire:model.live="periodicidadSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="plan-select"
                        >
                            <option value="bimensual">Bimensual</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="cuatrimestral">Cuatrimestral</option>
                            <option value="semestral">Semestral</option>
                        </select>
                    </div>
                    <div class="plan-field plan-field--full">
                        <label class="plan-label">Observaciones sobre el seguimiento</label>
                        <textarea
                            wire:model.lazy="observacionesSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="plan-textarea"
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
                <label class="plan-label">Fecha de la firma presencial</label>
                <input
                    type="date"
                    wire:model.lazy="fechaFirmaPresencial"
                    wire:change="guardarFechaFirma"
                    class="plan-input"
                >
            </div>

            @if($this->puedeActivarse)
            <div class="plan-firma-lista-ok">
                <i data-lucide="check-circle" class="icon-14"></i>
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
<div class="plan-drawer-overlay" wire:click="cerrarDrawer">
    <div class="plan-drawer" wire:click.stop x-data="{ seleccion: @entangle('fichasSeleccionadas') }">
        <div class="plan-drawer-header">
            <div class="plan-drawer-title">Historia social — fichas</div>
            <button wire:click="cerrarDrawer" aria-label="Cerrar">
                <i data-lucide="x" class="icon-16"></i>
            </button>
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

        <div class="plan-drawer-footer">
            <button wire:click="cerrarDrawer" class="plan-btn">Cancelar</button>
            <button
                x-on:click="$wire.aplicarSeleccionFichas(seleccion)"
                class="plan-btn plan-btn--primary"
            >
                <i data-lucide="check" class="icon-13"></i>
                Aplicar selección
            </button>
        </div>
    </div>
</div>
@endif

{{-- ============================================================
     MODAL DE MOTIVO OBLIGATORIO
     ============================================================ --}}
@if($modalMotivoAbierto)
<div class="plan-modal-overlay">
    <div class="plan-modal">
        <div class="plan-modal-title">Cambio en plan firmado</div>
        <div class="plan-modal-sub">
            Para realizar este cambio en un plan activo, indica el motivo.
            Quedará registrado en el historial del plan.
        </div>
        <textarea
            wire:model="motivoTexto"
            class="plan-textarea"
            rows="3"
            placeholder="ej: se actualizó la ficha de vivienda tras visita domiciliaria…"
            autofocus
        ></textarea>
        <div class="plan-modal-footer">
            <button wire:click="cancelarCambio" class="plan-btn">Cancelar</button>
            <button
                wire:click="confirmarCambioConMotivo"
                class="plan-btn plan-btn--primary"
                @if(empty(trim($motivoTexto))) disabled @endif
            >
                <i data-lucide="check" class="icon-13"></i>
                Confirmar cambio
            </button>
        </div>
    </div>
</div>
@endif

</div>{{-- fin plan-layout --}}
