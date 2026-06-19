{{-- Ficha del ciudadano — Capa 1 --}}
{{-- Pivota sobre Ciudadano, no sobre HistoriaSocial --}}
<div>
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

    $rolActual = auth()->user()->getRoleNames()->first() ?? '—';
@endphp

{{-- ===== CABECERA ===== --}}
<div style="
    background: var(--color-surface, #fff);
    border-bottom: 1px solid var(--color-border, #e5e7eb);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
">
    <div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <h1 style="margin:0;font-size:1.35rem;font-weight:700;color:var(--color-text-primary,#111827);">
                {{ $ciudadano->nombre_completo ?: '—' }}
            </h1>
            <span style="
                font-size:.72rem;font-weight:600;padding:.2rem .55rem;border-radius:999px;
                background:{{ $nivelBadge['bg'] }};color:{{ $nivelBadge['fg'] }};
            ">{{ $nivelBadge['label'] }}</span>
        </div>
        <div style="margin-top:.35rem;font-size:.85rem;color:var(--color-text-secondary,#6b7280);display:flex;gap:1rem;flex-wrap:wrap;">
            @if($docActivo)
                <span>
                    {{ strtoupper($docActivo->tipo) }}: {{ $docActivo->valor }}
                </span>
            @else
                <span style="opacity:.5;">Sin documento activo</span>
            @endif
            @if($edad !== null)
                <span>{{ $edad }} años</span>
            @endif
            <span>Rol: <strong>{{ $rolActual }}</strong></span>
        </div>
    </div>

    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">

        {{-- Botones de atención e historia social --}}
        @if($this->puedeCrearAtencion)
        <button wire:click="abrirModalAtencion" type="button" class="ficha-btn">
            <i data-lucide="message-square-plus" style="width:14px;height:14px" aria-hidden="true"></i>
            Nueva atención
        </button>
        @endif

        @if($this->puedeAbrirHistoria)
        <button
            wire:click="abrirHistoriaSocial"
            wire:confirm="¿Abrir historia social para este ciudadano? Esta acción asignará la historia a tu UO."
            type="button"
            class="ficha-btn ficha-btn--primary"
        >
            <i data-lucide="folder-plus" style="width:14px;height:14px" aria-hidden="true"></i>
            Abrir historia social
        </button>
        @elseif($historiaSocial && $puedeVerHS)
        <a
            wire:navigate
            href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}"
            class="ficha-btn ficha-btn--primary"
        >
            <i data-lucide="folder-open" style="width:14px;height:14px" aria-hidden="true"></i>
            Ver historia social
        </a>
        @endif

        {{-- Botones de edición de datos --}}
        @if($modoEdicion)
            <button wire:click="guardar" type="button"
                style="font-size:.82rem;padding:.35rem .9rem;border-radius:6px;border:none;cursor:pointer;
                       background:var(--color-primary,#3b82f6);color:#fff;font-weight:600;">
                Guardar cambios
            </button>
            <button wire:click="cancelarEdicion" type="button"
                style="font-size:.82rem;padding:.35rem .9rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);
                       background:#fff;cursor:pointer;color:var(--color-text-primary,#111827);">
                Cancelar
            </button>
        @elseif($puedeEditar)
            <button wire:click="activarEdicion" type="button"
                style="font-size:.82rem;padding:.35rem .9rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);
                       background:#fff;cursor:pointer;display:flex;align-items:center;gap:.4rem;color:var(--color-text-primary,#111827);">
                <i data-lucide="pencil" style="width:14px;height:14px;" aria-hidden="true"></i>
                Editar datos
            </button>
        @endif
    </div>
</div>

{{-- ===== VALIDACIÓN ===== --}}
@if($errors->any())
    <div style="margin:1rem 1.5rem 0;padding:.75rem 1rem;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:.85rem;">
        <ul style="margin:0;padding-left:1.2rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- ===== CONTENIDO DOS COLUMNAS ===== --}}
<div class="container-fluid" style="padding:1.25rem 1.5rem;">
    <div class="row g-3">

        {{-- ===================== COLUMNA PRINCIPAL ===================== --}}
        <div class="col-lg-8">

            {{-- ——— Identificación y contacto ——— --}}
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                <h2 style="font-size:.95rem;font-weight:600;margin:0 0 1rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="user" style="width:16px;height:16px;" aria-hidden="true"></i>
                    Identificación y contacto
                </h2>

                <div class="row g-3">
                    {{-- Nombre --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Nombre</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="nombre"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $nombre ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Apellido 1 --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Apellido 1</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="apellido1"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $apellido1 ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Apellido 2 --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Apellido 2</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="apellido2"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $apellido2 ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Fecha nacimiento --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Fecha de nacimiento</label>
                        @if($modoEdicion)
                            <input type="date" wire:model="fechaNacimiento"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">
                                {{ $fechaNacimiento ? \Carbon\Carbon::parse($fechaNacimiento)->format('d/m/Y') : '—' }}
                            </span>
                        @endif
                    </div>
                    {{-- Sexo --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Sexo</label>
                        @if($modoEdicion)
                            <select wire:model="sexo"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                                <option value="">— Seleccionar —</option>
                                <option value="H">Hombre</option>
                                <option value="M">Mujer</option>
                                <option value="NB">No binario</option>
                            </select>
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">
                                {{ match($sexo) { 'H' => 'Hombre', 'M' => 'Mujer', 'NB' => 'No binario', default => ($sexo ?: '—') } }}
                            </span>
                        @endif
                    </div>
                    {{-- Alias --}}
                    <div class="col-sm-4">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Alias / apodo</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="alias"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $alias ?: '—' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Separador contacto --}}
                <hr style="border:none;border-top:1px solid var(--color-border,#e5e7eb);margin:1rem 0;">

                <div class="row g-3">
                    {{-- Domicilio --}}
                    <div class="col-12">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Domicilio</label>
                        @if($modoEdicion)
                            <input type="text" wire:model="direccionTexto"
                                placeholder="Texto libre — se normaliza al guardar"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $direccionTexto ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Teléfono --}}
                    <div class="col-sm-6">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Teléfono</label>
                        @if($modoEdicion)
                            <input type="tel" wire:model="telefono"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $telefono ?: '—' }}</span>
                        @endif
                    </div>
                    {{-- Email --}}
                    <div class="col-sm-6">
                        <label style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);font-weight:500;display:block;margin-bottom:.25rem;">Email</label>
                        @if($modoEdicion)
                            <input type="email" wire:model="email"
                                style="width:100%;padding:.4rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                        @else
                            <span style="font-size:.9rem;color:var(--color-text-primary,#111827);">{{ $email ?: '—' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Primera demanda (inmutable) --}}
                @if($ciudadano->primera_demanda)
                    <div style="margin-top:1rem;padding:.75rem 1rem;background:var(--color-bg-subtle,#f9fafb);border-radius:8px;border-left:3px solid var(--color-primary,#3b82f6);">
                        <div style="font-size:.75rem;color:var(--color-text-secondary,#6b7280);margin-bottom:.25rem;">Primera demanda registrada en el alta</div>
                        <blockquote style="margin:0;font-size:.88rem;color:var(--color-text-primary,#111827);font-style:italic;">
                            "{{ $ciudadano->primera_demanda }}"
                        </blockquote>
                    </div>
                @endif
            </div>

            {{-- ——— Documentos de identidad ——— --}}
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h2 style="font-size:.95rem;font-weight:600;margin:0;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                        <i data-lucide="id-card" style="width:16px;height:16px;" aria-hidden="true"></i>
                        Documentos de identidad
                    </h2>
                    @if($puedeEditar)
                        <button wire:click="abrirModalDocumento" type="button"
                            style="font-size:.78rem;padding:.3rem .75rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);
                                   background:#fff;cursor:pointer;display:flex;align-items:center;gap:.35rem;">
                            <i data-lucide="plus" style="width:13px;height:13px;" aria-hidden="true"></i>
                            Añadir documento
                        </button>
                    @endif
                </div>

                @if($documentos->isEmpty())
                    <p style="font-size:.85rem;color:var(--color-text-secondary,#6b7280);margin:0;">Sin documentos registrados.</p>
                @else
                    <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--color-border,#e5e7eb);">
                                <th style="text-align:left;padding:.4rem .5rem;font-weight:600;color:var(--color-text-secondary,#6b7280);font-size:.75rem;">Tipo</th>
                                <th style="text-align:left;padding:.4rem .5rem;font-weight:600;color:var(--color-text-secondary,#6b7280);font-size:.75rem;">Valor</th>
                                <th style="text-align:left;padding:.4rem .5rem;font-weight:600;color:var(--color-text-secondary,#6b7280);font-size:.75rem;">Inicio</th>
                                <th style="text-align:left;padding:.4rem .5rem;font-weight:600;color:var(--color-text-secondary,#6b7280);font-size:.75rem;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentos as $doc)
                            @php
                                $esActivo  = $doc->fecha_fin === null;
                                $rowOpacity = $esActivo ? '1' : '.45';
                                $estadoDoc  = $esActivo ? 'Activo' : 'Sustituido';
                                $estadoBg   = $esActivo ? '#dcfce7' : '#f3f4f6';
                                $estadoFg   = $esActivo ? '#166534' : '#374151';
                            @endphp
                            <tr style="border-bottom:1px solid var(--color-border,#e5e7eb);opacity:{{ $rowOpacity }};">
                                <td style="padding:.5rem .5rem;">{{ strtoupper($doc->tipo) }}</td>
                                <td style="padding:.5rem .5rem;font-family:monospace;">{{ $doc->valor }}</td>
                                <td style="padding:.5rem .5rem;">{{ $doc->fecha_inicio?->format('d/m/Y') }}</td>
                                <td style="padding:.5rem .5rem;">
                                    <span style="font-size:.72rem;font-weight:600;padding:.15rem .45rem;border-radius:999px;background:{{ $estadoBg }};color:{{ $estadoFg }};">
                                        {{ $estadoDoc }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p style="margin:.75rem 0 0;font-size:.75rem;color:var(--color-text-secondary,#6b7280);">
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
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h2 style="font-size:.95rem;font-weight:600;margin:0;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                        <i data-lucide="users" style="width:16px;height:16px;" aria-hidden="true"></i>
                        Relaciones
                    </h2>
                    @if($puedeEditarRel)
                        <button wire:click="abrirModalNuevaRelacion" type="button"
                            style="font-size:.78rem;padding:.3rem .75rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);
                                   background:#fff;cursor:pointer;display:flex;align-items:center;gap:.35rem;">
                            <i data-lucide="plus" style="width:13px;height:13px;" aria-hidden="true"></i>
                            Añadir relación
                        </button>
                    @endif
                </div>

                @if($relacionMensaje)
                    <div style="margin-bottom:.75rem;padding:.5rem .75rem;background:#dcfce7;color:#166534;font-size:.82rem;border-radius:6px;">
                        {{ $relacionMensaje }}
                    </div>
                @endif

                @if($relacionesActivas->isEmpty())
                    <p style="font-size:.85rem;color:var(--color-text-secondary,#6b7280);margin:0;">
                        Sin relaciones registradas.
                    </p>
                @else
                    <div style="display:flex;flex-direction:column;gap:.25rem;">
                        @foreach($relacionesActivas as $rel)
                        @php
                            $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                            $nombreRel    = $rel->ciudadanoRelacionado?->nombre_completo ?? '—';
                            $fichaUrl     = $rel->ciudadano_relacionado_id
                                ? route('ciudadania.ciudadano.ficha', $rel->ciudadano_relacionado_id)
                                : null;
                        @endphp
                        <div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .5rem;border-radius:6px;
                                    {{ $puedeEditarRel ? 'cursor:pointer;' : '' }}background:transparent;"
                             {{ $puedeEditarRel ? "wire:click=\"abrirModalEditarRelacion({$rel->id})\"" : '' }}>
                            <span style="font-size:.72rem;font-weight:600;padding:.15rem .45rem;border-radius:999px;
                                         background:#f3f4f6;color:#374151;white-space:nowrap;">
                                {{ $etiquetaTipo }}
                            </span>
                            @if($fichaUrl)
                                <a wire:navigate href="{{ $fichaUrl }}"
                                   style="font-size:.88rem;color:var(--color-primary,#3b82f6);text-decoration:none;flex:1;"
                                   wire:click.stop>
                                    {{ $nombreRel }}
                                </a>
                            @else
                                <span style="font-size:.88rem;flex:1;">{{ $nombreRel }}</span>
                            @endif
                            @if($puedeEditarRel)
                                <i data-lucide="chevron-right" style="width:14px;height:14px;color:var(--color-text-secondary,#6b7280);" aria-hidden="true"></i>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif

                @if($relacionesHist->isNotEmpty())
                    <div style="margin-top:.75rem;">
                        <button wire:click="toggleHistorialRelaciones" type="button"
                            style="font-size:.78rem;color:var(--color-text-secondary,#6b7280);background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;gap:.3rem;">
                            <i data-lucide="{{ $mostrarHistorialRelaciones ? 'chevron-up' : 'chevron-down' }}" style="width:13px;height:13px;" aria-hidden="true"></i>
                            {{ $mostrarHistorialRelaciones ? 'Ocultar historial' : "Ver historial ({$relacionesHist->count()})" }}
                        </button>
                        @if($mostrarHistorialRelaciones)
                            <div style="margin-top:.5rem;display:flex;flex-direction:column;gap:.2rem;opacity:.6;">
                                @foreach($relacionesHist as $rel)
                                @php
                                    $etiquetaTipo = $rel->tipoRelacion?->etiqueta ?? $rel->tipo_relacion;
                                @endphp
                                <div style="display:flex;align-items:center;gap:.75rem;padding:.35rem .5rem;font-size:.82rem;">
                                    <span style="font-size:.7rem;font-weight:600;padding:.1rem .4rem;border-radius:999px;
                                                 background:#f3f4f6;color:#6b7280;white-space:nowrap;">
                                        {{ $etiquetaTipo }}
                                    </span>
                                    <span style="flex:1;">{{ $rel->ciudadanoRelacionado?->nombre_completo ?? '—' }}</span>
                                    <span style="font-size:.72rem;color:#9ca3af;">
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
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;">
                <h2 style="font-size:.95rem;font-weight:600;margin:0 0 .75rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="home" style="width:16px;height:16px;" aria-hidden="true"></i>
                    Convivientes
                </h2>
                <div style="display:flex;flex-direction:column;gap:.25rem;">
                    @foreach($ucMiembros as $miembro)
                    @php
                        $nombreMiembro = $miembro->ciudadano?->nombre_completo ?? '—';
                        $fichaUrl      = $miembro->ciudadano_id
                            ? route('ciudadania.ciudadano.ficha', $miembro->ciudadano_id)
                            : null;
                    @endphp
                    <div style="display:flex;align-items:center;gap:.75rem;padding:.35rem .5rem;font-size:.88rem;">
                        @if($miembro->tipo_relacion_etiqueta)
                            <span style="font-size:.72rem;font-weight:600;padding:.1rem .4rem;border-radius:999px;
                                         background:#f3f4f6;color:#374151;white-space:nowrap;">
                                {{ $miembro->tipo_relacion_etiqueta }}
                            </span>
                        @endif
                        @if($fichaUrl)
                            <a wire:navigate href="{{ $fichaUrl }}"
                               style="color:var(--color-primary,#3b82f6);text-decoration:none;flex:1;">
                                {{ $nombreMiembro }}
                            </a>
                        @else
                            <span style="flex:1;">{{ $nombreMiembro }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ——— Historial de atenciones ——— --}}
            @if($this->historialAtenciones->isNotEmpty() || $this->puedeCrearAtencion)
            <div class="ficha-section" id="ficha-atencion-historial" style="margin-bottom:1rem;">
                <div class="ficha-section-header">
                    <div class="ficha-section-title">
                        <i data-lucide="history" style="width:14px;height:14px" aria-hidden="true"></i>
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
                        class="ficha-atencion-ver"
                        @click="expandido = !expandido"
                        :aria-expanded="expandido"
                        type="button"
                    >
                        <span x-text="expandido ? 'Ocultar' : 'Ver detalle'"></span>
                        <i data-lucide="chevron-down" style="width:12px;height:12px"
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

        </div>{{-- /col-lg-8 --}}

        {{-- ===================== COLUMNA LATERAL ===================== --}}
        <div class="col-lg-4">

            {{-- ——— Banner historia social ——— --}}
            @if($historiaSocial)
                <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;border-left:4px solid var(--color-primary,#3b82f6);">
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem;">
                        <i data-lucide="file-text" style="width:16px;height:16px;color:var(--color-primary,#3b82f6);" aria-hidden="true"></i>
                        <span style="font-size:.85rem;font-weight:600;color:var(--color-primary,#3b82f6);">Historia social activa</span>
                    </div>
                    <p style="font-size:.8rem;color:var(--color-text-secondary,#6b7280);margin:0 0 .75rem;">
                        Esta persona tiene historia social registrada en el sistema.
                    </p>
                    @if($puedeVerHS)
                        <a wire:navigate href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}"
                            style="font-size:.8rem;font-weight:600;color:var(--color-primary,#3b82f6);text-decoration:none;">
                            Ir a HS →
                        </a>
                    @else
                        <span style="font-size:.8rem;font-weight:600;opacity:.4;cursor:default;"
                              title="Requiere rol de intervención">
                            Ir a HS →
                        </span>
                    @endif
                </div>
            @endif

            {{-- ——— Otras prestaciones ——— --}}
            @if($prestaciones->isNotEmpty())
                <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                    <h2 style="font-size:.9rem;font-weight:600;margin:0 0 .75rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                        <i data-lucide="layers" style="width:16px;height:16px;" aria-hidden="true"></i>
                        Otras prestaciones
                    </h2>
                    <div style="display:flex;flex-direction:column;gap:.5rem;">
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
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border,#e5e7eb);">
                            <div>
                                <div style="font-size:.83rem;font-weight:500;color:var(--color-text-primary,#111827);">{{ $pres->descripcion }}</div>
                                <div style="font-size:.72rem;color:var(--color-text-secondary,#6b7280);">{{ $pres->fecha_inicio?->format('d/m/Y') }}</div>
                            </div>
                            <span style="font-size:.7rem;font-weight:600;padding:.15rem .4rem;border-radius:999px;white-space:nowrap;background:{{ $bg }};color:{{ $fg }};">
                                {{ $estadoLabel }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ——— Accesos recientes al expediente ——— --}}
            @if($this->puedeVerAccesos && $actividadRec->isNotEmpty())
                @php
                    $uoExpediente = $historiaSocial?->unidad_organizativa_id;
                @endphp
                <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                    <div class="accesos-panel__header">
                        <span style="font-size:.9rem;font-weight:600;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                            <i data-lucide="shield-check" style="width:16px;height:16px;" aria-hidden="true"></i>
                            Accesos recientes al expediente
                        </span>
                        @if($this->puedeVerTodosLosAccesos)
                            {{-- TODO: modal historial completo --}}
                            <a href="#" class="accesos-panel__ver-todo">Ver todo</a>
                        @endif
                    </div>

                    @foreach($actividadRec as $acceso)
                        @php
                            $esPropio     = $acceso->user_id === auth()->id();
                            $uoAcceso     = $acceso->contexto['unidad_organizativa_id'] ?? null;
                            // Aproximación con la UO actual si no se registró en contexto
                            $uoAcceso     = $uoAcceso ?? $acceso->user?->profesional?->unidad_organizativa_id;
                            $esOtraUo     = $uoExpediente !== null && $uoAcceso !== null && $uoAcceso !== $uoExpediente;
                            $esCambio     = in_array($acceso->accion?->value, ['crear', 'editar', 'eliminar']);
                            $esAnomalos   = $esOtraUo && $esCambio;
                            $esSospechoso = $esOtraUo && ! $esCambio;
                        @endphp

                        <div class="acceso-fila
                            {{ $esPropio     ? 'acceso-fila--propio'      : '' }}
                            {{ $esAnomalos   ? 'acceso-fila--anomalo'     : '' }}
                            {{ $esSospechoso ? 'acceso-fila--sospechoso'  : '' }}">

                            <div class="acceso-fila__quien">
                                <span class="acceso-fila__nombre">
                                    {{ $acceso->user?->profesional?->nombre_completo ?? $acceso->user?->name ?? '—' }}
                                </span>
                                @if($esOtraUo)
                                    <span class="acceso-fila__badge-uo" title="Profesional de otra UO">Otra UO</span>
                                @endif
                            </div>

                            <div class="acceso-fila__detalle">
                                <span class="acceso-fila__accion acceso-fila__accion--{{ $acceso->accion?->value }}">
                                    {{ $acceso->accion?->etiqueta() ?? '—' }}
                                </span>
                                @if($esAnomalos)
                                    <span class="acceso-fila__alerta" title="Modificación desde otra UO — revisar">
                                        <i data-lucide="alert-triangle" style="width:14px;height:14px;" aria-hidden="true"></i>
                                    </span>
                                @endif
                                <span class="acceso-fila__fecha">{{ $acceso->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>{{-- /col-lg-4 --}}

    </div>{{-- /row --}}
</div>

{{-- ===== MODAL RELACIÓN ===== --}}
@if($modalRelacionAbierto)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;"
         wire:click.self="cerrarModalRelacion">
        <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 20px 40px rgba(0,0,0,.15);">

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="margin:0;font-size:1rem;font-weight:700;">
                    {{ $relacionId ? 'Editar relación' : 'Nueva relación' }}
                </h3>
                <button wire:click="cerrarModalRelacion" type="button"
                    style="border:none;background:none;cursor:pointer;padding:.25rem;color:var(--color-text-secondary,#6b7280);">
                    <i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Tipo de relación (solo en creación) --}}
            @if(! $relacionId)
            <div style="margin-bottom:.85rem;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">
                    Tipo de relación <span style="color:#ef4444;">*</span>
                </label>
                <select wire:model="relacionTipo"
                    style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                    <option value="">— Seleccionar —</option>
                    @foreach($this->tiposRelacion as $slug => $etiqueta)
                        <option value="{{ $slug }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('relacionTipo')
                    <span style="font-size:.75rem;color:#ef4444;">{{ $message }}</span>
                @enderror
            </div>

            {{-- Buscador ciudadano (solo en creación) --}}
            <div style="margin-bottom:.85rem;position:relative;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">
                    Ciudadano <span style="color:#ef4444;">*</span>
                </label>
                @if($this->ciudadanoSeleccionadoRelacion)
                    <div style="display:flex;align-items:center;gap:.5rem;padding:.45rem .6rem;border:1px solid var(--color-primary,#3b82f6);border-radius:6px;background:#eff6ff;">
                        <span style="font-size:.88rem;flex:1;">{{ $this->ciudadanoSeleccionadoRelacion->nombre_completo }}</span>
                        <button type="button" wire:click="$set('relacionCiudadanoSeleccionado', null)"
                            style="border:none;background:none;cursor:pointer;color:#6b7280;padding:0;">
                            <i data-lucide="x" style="width:14px;height:14px;" aria-hidden="true"></i>
                        </button>
                    </div>
                @else
                    <input type="text" wire:model.live="relacionBusqueda"
                        placeholder="Escribir nombre (mín. 2 caracteres)…"
                        style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                    @if($this->relacionResultadosBusqueda->isNotEmpty())
                        <div style="position:absolute;left:0;right:0;background:#fff;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;
                                    box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:10;max-height:220px;overflow-y:auto;margin-top:2px;">
                            @foreach($this->relacionResultadosBusqueda as $sug)
                                <button type="button" wire:click="seleccionarCiudadanoRelacion({{ $sug->id }})"
                                    style="width:100%;text-align:left;padding:.55rem .75rem;border:none;background:none;cursor:pointer;font-size:.85rem;
                                           border-bottom:1px solid var(--color-border,#e5e7eb);">
                                    {{ $sug->nombre_completo }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                @error('relacionCiudadanoSeleccionado')
                    <span style="font-size:.75rem;color:#ef4444;">{{ $message }}</span>
                @enderror
            </div>

            {{-- Fecha inicio (solo en creación) --}}
            <div style="margin-bottom:.85rem;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">
                    Fecha de inicio <span style="color:#ef4444;">*</span>
                </label>
                <input type="date" wire:model="relacionFechaInicio"
                    style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                @error('relacionFechaInicio')
                    <span style="font-size:.75rem;color:#ef4444;">{{ $message }}</span>
                @enderror
            </div>
            @endif

            {{-- Observaciones (creación y edición) --}}
            <div style="margin-bottom:1.25rem;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Observaciones</label>
                <textarea wire:model="relacionObservaciones" rows="3" placeholder="Opcional…"
                    style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;resize:vertical;"></textarea>
                @error('relacionObservaciones')
                    <span style="font-size:.75rem;color:#ef4444;">{{ $message }}</span>
                @enderror
            </div>

            <div style="display:flex;gap:.5rem;justify-content:space-between;align-items:center;">
                <div>
                    @if($relacionId)
                        <button wire:click="cerrarRelacion({{ $relacionId }})" type="button"
                            wire:confirm="¿Confirmar el cierre de esta relación? Se establecerá fecha de fin hoy."
                            style="padding:.45rem 1rem;border-radius:6px;border:1px solid #fca5a5;background:#fff;font-size:.82rem;cursor:pointer;color:#dc2626;">
                            Cerrar relación
                        </button>
                    @endif
                </div>
                <div style="display:flex;gap:.5rem;">
                    <button wire:click="cerrarModalRelacion" type="button"
                        style="padding:.45rem 1rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);background:#fff;font-size:.85rem;cursor:pointer;">
                        Cancelar
                    </button>
                    <button wire:click="guardarRelacion" type="button"
                        style="padding:.45rem 1rem;border-radius:6px;border:none;background:var(--color-primary,#3b82f6);color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ===== MODAL NUEVO DOCUMENTO ===== --}}
@if($modalDocumento)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;" wire:click.self="cerrarModalDocumento">
        <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:420px;box-shadow:0 20px 40px rgba(0,0,0,.15);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="margin:0;font-size:1rem;font-weight:700;">Añadir documento de identidad</h3>
                <button wire:click="cerrarModalDocumento" type="button"
                    style="border:none;background:none;cursor:pointer;padding:.25rem;color:var(--color-text-secondary,#6b7280);">
                    <i data-lucide="x" style="width:18px;height:18px;" aria-hidden="true"></i>
                </button>
            </div>
            <p style="font-size:.8rem;color:var(--color-text-secondary,#6b7280);margin:0 0 1rem;">
                El documento actual recibirá fecha de fin. El historial se conserva íntegro.
            </p>

            <div style="margin-bottom:.85rem;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Tipo de documento</label>
                <select wire:model="nuevoTipoDocumento"
                    style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                    <option value="nif">DNI / NIF</option>
                    <option value="nie">NIE</option>
                    <option value="pasaporte">Pasaporte</option>
                </select>
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.3rem;">Número de documento</label>
                <input type="text" wire:model="nuevoValorDocumento" placeholder="Ej.: 12345678A"
                    style="width:100%;padding:.45rem .6rem;border:1px solid var(--color-border,#e5e7eb);border-radius:6px;font-size:.88rem;">
                @error('nuevoValorDocumento')
                    <span style="font-size:.75rem;color:#ef4444;">{{ $message }}</span>
                @enderror
            </div>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                <button wire:click="cerrarModalDocumento" type="button"
                    style="padding:.45rem 1rem;border-radius:6px;border:1px solid var(--color-border,#e5e7eb);background:#fff;font-size:.85rem;cursor:pointer;">
                    Cancelar
                </button>
                <button wire:click="guardarDocumento" type="button"
                    style="padding:.45rem 1rem;border-radius:6px;border:none;background:var(--color-primary,#3b82f6);color:#fff;font-size:.85rem;font-weight:600;cursor:pointer;">
                    Guardar documento
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ===== MODAL NUEVA ATENCIÓN ===== --}}
@if($this->modalAtencionAbierto)
<div
    class="ficha-modal-overlay"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalAtencion()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-atencion-titulo"
>
    <div class="ficha-modal">
        <div class="ficha-modal-header">
            <h2 id="modal-atencion-titulo" class="ficha-modal-titulo">Nueva atención</h2>
            <button wire:click="cerrarModalAtencion" aria-label="Cerrar" class="ficha-modal-cerrar" type="button">
                <i data-lucide="x" style="width:16px;height:16px" aria-hidden="true"></i>
            </button>
        </div>

        <div class="ficha-modal-body">

            <div class="ficha-field">
                <label class="ficha-label" for="at-fecha">Fecha</label>
                <input
                    type="date"
                    id="at-fecha"
                    wire:model="atencionFecha"
                    class="ficha-input"
                    max="{{ now()->toDateString() }}"
                >
                @error('atencionFecha') <span class="ficha-error">{{ $message }}</span> @enderror
            </div>

            @if(! auth()->user()->hasRole('consulta_basica'))
            <div class="ficha-field">
                <label class="ficha-label">Tipo de atención</label>
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
                <label class="ficha-label" for="at-demanda">Demanda del ciudadano</label>
                <textarea
                    id="at-demanda"
                    wire:model="atencionDemanda"
                    class="ficha-textarea"
                    rows="3"
                    placeholder="Qué solicita o comunica el ciudadano…"
                ></textarea>
                @error('atencionDemanda') <span class="ficha-error">{{ $message }}</span> @enderror
            </div>

            <div class="ficha-field">
                <label class="ficha-label" for="at-respuesta">Respuesta / actuación</label>
                <textarea
                    id="at-respuesta"
                    wire:model="atencionRespuesta"
                    class="ficha-textarea"
                    rows="2"
                    placeholder="Qué se le informa, orienta o tramita…"
                ></textarea>
            </div>

        </div>

        <div class="ficha-modal-footer">
            <button wire:click="cerrarModalAtencion" class="ficha-btn" type="button">Cancelar</button>
            <button wire:click="guardarAtencion" class="ficha-btn ficha-btn--primary" type="button">
                <i data-lucide="check" style="width:13px;height:13px" aria-hidden="true"></i>
                Guardar atención
            </button>
        </div>
    </div>
</div>
@endif

</div>
