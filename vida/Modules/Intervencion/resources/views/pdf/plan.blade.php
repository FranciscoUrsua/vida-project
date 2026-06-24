<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 28mm 20mm 26mm 20mm;
}

body {
    font-family: 'Source Sans 3', 'Source Sans Pro', Arial, sans-serif;
    font-size: 9.5pt;
    line-height: 1.5;
    color: #1D160E;
    background: #FAF7F1;
}

/* ---- Cabecera institucional repetida por página ---- */
#cabecera-institucional {
    position: fixed;
    top: -22mm;
    left: 0;
    right: 0;
    height: 18mm;
    border-bottom: 1.5px solid #2A5B8A;
    padding-bottom: 4px;
}

/* ---- Pie repetido por página ---- */
#pie-documento {
    position: fixed;
    bottom: -20mm;
    left: 0;
    right: 0;
    height: 16mm;
    border-top: 1px solid #C7BFB5;
    padding-top: 4px;
    font-size: 7.5pt;
    color: #8A7F76;
}

.pagenum::before {
    content: counter(page);
}

/* ---- Secciones del cuerpo ---- */
.piso-seccion {
    margin-bottom: 7mm;
    page-break-inside: avoid;
}

.piso-seccion-titulo {
    background-color: #2A5B8A;
    color: #FFFFFF;
    font-size: 8.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 4px 8px;
    margin-bottom: 5px;
}

.piso-seccion-cuerpo {
    padding: 4px 8px;
    font-size: 9.5pt;
    line-height: 1.5;
    color: #1D160E;
}

.piso-tabla {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
}

.piso-tabla th {
    background-color: #EDE8DF;
    border-bottom: 1px solid #C7BFB5;
    text-align: left;
    padding: 3px 6px;
    font-weight: 600;
    color: #3A2F26;
}

.piso-tabla td {
    border-bottom: 1px solid #E8E2D9;
    padding: 3px 6px;
    vertical-align: top;
}

.piso-tabla tr:last-child td {
    border-bottom: none;
}
</style>
</head>
<body>

{{-- Cabecera institucional repetida en todas las páginas --}}
<div id="cabecera-institucional">
    @if(!empty($estilo['logo_cabecera']))
        <img src="{{ storage_path('app/' . $estilo['logo_cabecera']) }}"
             alt="Logo institucional" style="height: 12mm; float: left; margin-right: 8px;">
    @endif
    <div style="overflow: hidden; padding-top: 2px;">
        <span style="font-size: 10pt; font-weight: 600; color: #2A5B8A;">
            {{ $estilo['nombre_unidad_cabecera'] ?? 'Servicios Sociales' }}
        </span>
        @if(!empty($estilo['direccion_cabecera']))
            <br>
            <span style="font-size: 8pt; color: #5A4E44;">
                {{ $estilo['direccion_cabecera'] }}
                @if(!empty($estilo['telefono_cabecera']))
                    · {{ $estilo['telefono_cabecera'] }}
                @endif
            </span>
        @endif
    </div>
</div>

{{-- Pie repetido en todas las páginas --}}
<div id="pie-documento">
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td style="text-align: left;">
                {!! $estilo['html_pie'] ?? 'Plan de Intervención Social — VIDA360' !!}
            </td>
            <td style="text-align: right;">
                Historia Social #{{ $plan->historia_id }} ·
                Versión {{ $plan->version ?? 1 }} ·
                Pág. <span class="pagenum"></span>
            </td>
        </tr>
    </table>
</div>

{{-- Título del documento --}}
<div style="text-align: center; margin-bottom: 8mm; margin-top: 2mm;">
    <span style="font-size: 13pt; font-weight: 700; color: #2A5B8A; letter-spacing: 0.5px;">
        PLAN DE INTERVENCIÓN SOCIAL
    </span>
    <br>
    <span style="font-size: 8.5pt; color: #8A7F76;">
        Versión {{ $plan->version ?? 1 }} · {{ now()->format('d/m/Y') }}
    </span>
</div>

{{-- Sección: Datos de la persona --}}
@php $ciudadano = $plan->historia->ciudadano @endphp
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Datos de la persona</div>
    <div class="piso-seccion-cuerpo">
        <table width="100%" cellspacing="0" cellpadding="3">
            <tr>
                <td width="50%" style="vertical-align: top;">
                    <strong>Nombre:</strong> {{ $ciudadano->nombre_completo }}<br>
                    <strong>Fecha de nacimiento:</strong>
                        {{ $ciudadano->fecha_nacimiento
                            ? \Carbon\Carbon::parse($ciudadano->fecha_nacimiento)->format('d/m/Y')
                            : '—' }}<br>
                </td>
                <td width="50%" style="vertical-align: top;">
                    <strong>Domicilio:</strong> {{ $ciudadano->domicilio ?? '—' }}<br>
                    @if($plan->unidadConvivencia)
                        <strong>Unidad de convivencia:</strong>
                        {{ $plan->unidadConvivencia->miembrosActivos
                            ->map(fn ($m) => $m->ciudadano->nombre_completo)
                            ->implode(', ') }}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- Sección: Diagnóstico social --}}
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Diagnóstico social</div>
    <div class="piso-seccion-cuerpo">
        @if($plan->diagnostico_social)
            {!! nl2br(e($plan->diagnostico_social)) !!}
        @else
            <em style="color: #8A7F76;">Sin diagnóstico registrado.</em>
        @endif
    </div>
</div>

{{-- Sección: Objetivos generales --}}
@php
    $generales  = $plan->objetivosGenerales->all();
    $especificos = $plan->objetivosEspecificos->all();
@endphp
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Objetivos generales</div>
    <div class="piso-seccion-cuerpo">
        @if(count($generales) > 0)
            <ol style="margin: 0; padding-left: 16px;">
                @foreach($generales as $og)
                    <li style="margin-bottom: 3px;">{{ $og->texto }}</li>
                @endforeach
            </ol>
        @else
            <em style="color: #8A7F76;">Sin objetivos generales registrados.</em>
        @endif
    </div>
</div>

{{-- Sección: Objetivos específicos (por área temática) --}}
@if(count($especificos) > 0)
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Objetivos específicos</div>
    <div class="piso-seccion-cuerpo">
        <ol style="margin: 0; padding-left: 16px;">
            @foreach($especificos as $oe)
                <li style="margin-bottom: 3px;">
                    @if($oe->tipoFicha)
                        <span style="font-size: 7.5pt; color: #8A7F76; font-weight: 600;
                                     text-transform: uppercase; letter-spacing: 0.3px;">
                            {{ $oe->tipoFicha->nombre }}:
                        </span>
                    @endif
                    {{ $oe->texto }}
                </li>
            @endforeach
        </ol>
    </div>
</div>
@endif

{{-- Sección: Compromisos (dos columnas) --}}
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Compromisos</div>
    <div class="piso-seccion-cuerpo" style="padding: 0;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td width="49%" style="vertical-align: top; padding: 6px 8px;">
                    <div style="font-size: 8pt; font-weight: 600; color: #5A4E44;
                                text-transform: uppercase; letter-spacing: 0.3px;
                                margin-bottom: 4px; border-bottom: 1px solid #C7BFB5;
                                padding-bottom: 2px;">
                        Del Ayuntamiento
                    </div>
                    @if($plan->actuacionesAyuntamiento->isNotEmpty())
                        <table class="piso-tabla">
                            <thead>
                                <tr>
                                    <th>Prestación</th>
                                    <th style="width: 30%;">Responsable</th>
                                    <th style="width: 22%;">Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($plan->actuacionesAyuntamiento as $act)
                                    <tr>
                                        <td>{{ $act->prestacion->nombre ?? '—' }}</td>
                                        <td>{{ $act->responsable?->name ?? '—' }}</td>
                                        <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <em style="color: #8A7F76;">Sin compromisos registrados.</em>
                    @endif
                </td>
                <td width="2%" style="border-left: 1px solid #C7BFB5;"></td>
                <td width="49%" style="vertical-align: top; padding: 6px 8px;">
                    <div style="font-size: 8pt; font-weight: 600; color: #5A4E44;
                                text-transform: uppercase; letter-spacing: 0.3px;
                                margin-bottom: 4px; border-bottom: 1px solid #C7BFB5;
                                padding-bottom: 2px;">
                        De la persona
                    </div>
                    @if($plan->actuacionesCiudadano->isNotEmpty())
                        <table class="piso-tabla">
                            <thead>
                                <tr>
                                    <th>Compromiso</th>
                                    <th style="width: 30%;">Recurso</th>
                                    <th style="width: 22%;">Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($plan->actuacionesCiudadano as $act)
                                    <tr>
                                        <td>{{ $act->descripcion ?? '—' }}</td>
                                        <td>{{ $act->prestacion?->nombre ?? '—' }}</td>
                                        <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <em style="color: #8A7F76;">Sin compromisos registrados.</em>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- Sección: Profesionales y seguimiento --}}
<div class="piso-seccion">
    <div class="piso-seccion-titulo">Profesionales y seguimiento</div>
    <div class="piso-seccion-cuerpo">
        <table width="100%" cellspacing="0" cellpadding="3">
            <tr>
                <td width="70%" style="vertical-align: top;">
                    <strong>Profesionales participantes:</strong><br>
                    @if($plan->participantesActivos->isNotEmpty())
                        @foreach($plan->participantesActivos as $p)
                            {{ $p->profesional->name }} ({{ $p->rol_en_plan }})@if(!$loop->last), @endif
                        @endforeach
                    @else
                        <span style="color: #8A7F76;">—</span>
                    @endif
                </td>
                <td width="30%" style="vertical-align: top;">
                    <strong>Periodicidad de seguimiento:</strong><br>
                    {{ ucfirst($plan->periodicidad_seguimiento ?? 'trimestral') }}
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- Bloque de firmas --}}
<div class="piso-seccion" style="margin-top: 14mm;">
    <div class="piso-seccion-titulo">Firmas</div>
    <div class="piso-seccion-cuerpo">
        <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 12mm;">
            <tr>
                <td width="44%" style="border-top: 1px solid #1D160E; padding-top: 5px;
                                        text-align: center; font-size: 8.5pt;">
                    {{ $plan->profesionalResponsable?->name ?? '—' }}<br>
                    <span style="color: #8A7F76;">Trabajador/a Social de referencia</span><br>
                    <span style="color: #C7BFB5;">Fecha: ___________</span>
                </td>
                <td width="12%"></td>
                <td width="44%" style="border-top: 1px solid #1D160E; padding-top: 5px;
                                        text-align: center; font-size: 8.5pt;">
                    {{ $ciudadano->nombre_completo }}<br>
                    <span style="color: #8A7F76;">Persona interesada</span><br>
                    <span style="color: #C7BFB5;">Fecha: ___________</span>
                </td>
            </tr>
        </table>
    </div>
</div>

</body>
</html>
