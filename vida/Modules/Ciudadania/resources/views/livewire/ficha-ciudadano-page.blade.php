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

    <div style="display:flex;gap:.5rem;align-items:center;">
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

            {{-- ——— Unidad de convivencia ——— --}}
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;">
                <h2 style="font-size:.95rem;font-weight:600;margin:0 0 .75rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="home" style="width:16px;height:16px;" aria-hidden="true"></i>
                    Unidad de convivencia
                </h2>
                <p style="font-size:.85rem;color:var(--color-text-secondary,#6b7280);margin:0;">
                    Pendiente de implementación. Módulo UnidadConvivencia no disponible aún.
                </p>
            </div>

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

            {{-- ——— Actividad reciente ——— --}}
            @if($actividadRec->isNotEmpty())
                <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;margin-bottom:1rem;">
                    <h2 style="font-size:.9rem;font-weight:600;margin:0 0 .75rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                        <i data-lucide="activity" style="width:16px;height:16px;" aria-hidden="true"></i>
                        Actividad reciente
                    </h2>
                    @foreach($actividadRec as $entrada)
                        <div style="font-size:.8rem;color:var(--color-text-secondary,#6b7280);padding:.35rem 0;border-bottom:1px solid var(--color-border,#e5e7eb);">
                            {{ $entrada->descripcion ?? $entrada->accion ?? '—' }}
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ——— Permisos del rol activo ——— --}}
            <div style="background:var(--color-surface,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:10px;padding:1.25rem;">
                <h2 style="font-size:.9rem;font-weight:600;margin:0 0 .75rem;color:var(--color-text-primary,#111827);display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="shield" style="width:16px;height:16px;" aria-hidden="true"></i>
                    Permisos del rol <em>{{ $rolActual }}</em>
                </h2>
                <table style="width:100%;font-size:.78rem;border-collapse:collapse;">
                    <tbody>
                        <tr style="border-bottom:1px solid var(--color-border,#e5e7eb);">
                            <td style="padding:.4rem .25rem;color:var(--color-text-secondary,#6b7280);">Capa 1 — datos básicos</td>
                            <td style="padding:.4rem .25rem;text-align:right;">
                                <span style="color:{{ $puedeEditar ? '#166534' : '#6b7280' }};font-weight:600;font-size:.75rem;">
                                    {{ $puedeEditar ? 'Ver y editar' : 'Solo lectura' }}
                                </span>
                            </td>
                        </tr>
                        <tr style="border-bottom:1px solid var(--color-border,#e5e7eb);">
                            <td style="padding:.4rem .25rem;color:var(--color-text-secondary,#6b7280);">Historia social</td>
                            <td style="padding:.4rem .25rem;text-align:right;">
                                <span style="color:{{ $puedeVerHS ? '#166534' : '#6b7280' }};font-weight:600;font-size:.75rem;">
                                    {{ $puedeVerHS ? 'Acceso completo' : 'Sin acceso' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:.4rem .25rem;color:var(--color-text-secondary,#6b7280);">Capa 2 — situación social</td>
                            <td style="padding:.4rem .25rem;text-align:right;">
                                <span style="color:#6b7280;font-weight:600;font-size:.75rem;">Sin acceso</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>{{-- /col-lg-4 --}}

    </div>{{-- /row --}}
</div>

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

</div>
