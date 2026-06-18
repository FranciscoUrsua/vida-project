<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.4; }
    .cabecera { border-bottom: 2px solid #2A5B8A; padding-bottom: 8px; margin-bottom: 16px; }
    .cabecera__titulo { font-size: 14pt; font-weight: bold; color: #2A5B8A; }
    .cabecera__subtitulo { font-size: 10pt; color: #555; margin-top: 2px; }
    .seccion { margin-bottom: 14px; }
    .seccion__titulo { font-size: 10pt; font-weight: bold; text-transform: uppercase;
                       letter-spacing: .05em; color: #2A5B8A; border-bottom: 1px solid #ccc;
                       padding-bottom: 3px; margin-bottom: 6px; }
    .seccion__contenido { font-size: 9.5pt; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 4px; }
    th { background: #f0f4f8; text-align: left; padding: 4px 6px; font-weight: 600; border: 1px solid #ddd; }
    td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: top; }
    .firmas { margin-top: 32px; display: flex; gap: 40px; }
    .firma-bloque { flex: 1; border-top: 1px solid #333; padding-top: 6px; text-align: center; }
    .firma-bloque__nombre { font-size: 9pt; }
    .firma-bloque__fecha { font-size: 8pt; color: #666; margin-top: 2px; }
    .dato-fila { display: flex; gap: 8px; margin-bottom: 3px; }
    .dato-label { font-weight: 600; min-width: 140px; }
    .pie { margin-top: 20px; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 6px; }
</style>
</head>
<body>

{{-- Cabecera --}}
<div class="cabecera">
    <div class="cabecera__titulo">{{ $plan->tipoPlan?->nombre ?? 'Plan de Intervención Social' }}</div>
    <div class="cabecera__subtitulo">
        Versión {{ $plan->version }} ·
        Fecha: {{ $plan->fecha_inicio?->format('d/m/Y') ?? now()->format('d/m/Y') }}
    </div>
</div>

{{-- Datos del ciudadano --}}
<div class="seccion">
    <div class="seccion__titulo">Datos de la persona</div>
    <div class="seccion__contenido">
        @php $ciudadano = $plan->historia->ciudadano @endphp
        <div class="dato-fila">
            <span class="dato-label">Nombre y apellidos:</span>
            <span>{{ $ciudadano->nombre_completo }}</span>
        </div>
        <div class="dato-fila">
            <span class="dato-label">Fecha de nacimiento:</span>
            <span>{{ $ciudadano->fecha_nacimiento?->format('d/m/Y') }}</span>
        </div>
        <div class="dato-fila">
            <span class="dato-label">Domicilio:</span>
            <span>{{ $ciudadano->domicilio }}</span>
        </div>
        @if($plan->unidadConvivencia)
        <div class="dato-fila">
            <span class="dato-label">Unidad de convivencia:</span>
            <span>
                {{ $plan->unidadConvivencia->miembrosActivos->map(fn ($m) =>
                    $m->ciudadano->nombre_completo)->implode(', ') }}
            </span>
        </div>
        @endif
    </div>
</div>

{{-- Diagnóstico social --}}
@if($plan->diagnostico_social)
<div class="seccion">
    <div class="seccion__titulo">Diagnóstico social</div>
    <div class="seccion__contenido">{!! nl2br(e($plan->diagnostico_social)) !!}</div>
</div>
@endif

{{-- Objetivos --}}
@if($plan->objetivosGenerales->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Objetivos</div>
    @foreach($plan->objetivosGenerales as $og)
    <div class="seccion__contenido">
        <strong>{{ $loop->iteration }}. {{ $og->texto }}</strong>
        @if($og->objetivosEspecificos->isNotEmpty())
        <ul style="margin: 3px 0 6px 16px;">
            @foreach($og->objetivosEspecificos as $oe)
            <li>{{ $oe->texto }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Actuaciones del Ayuntamiento --}}
@if($plan->actuacionesAyuntamiento->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Compromisos del Ayuntamiento</div>
    <table>
        <thead>
            <tr>
                <th style="width:35%">Prestación</th>
                <th style="width:40%">Concreción</th>
                <th style="width:15%">Responsable</th>
                <th style="width:10%">Inicio previsto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->actuacionesAyuntamiento as $act)
            <tr>
                <td>{{ $act->prestacion->nombre }}</td>
                <td>{{ $act->descripcion_especifica ?? '—' }}</td>
                <td>{{ $act->responsable?->name ?? '—' }}</td>
                <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Compromisos del ciudadano --}}
@if($plan->actuacionesCiudadano->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Compromisos de la persona</div>
    <table>
        <thead>
            <tr>
                <th style="width:60%">Compromiso</th>
                <th style="width:30%">Recurso relacionado</th>
                <th style="width:10%">Inicio previsto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->actuacionesCiudadano as $act)
            <tr>
                <td>{{ $act->descripcion }}</td>
                <td>{{ $act->prestacion?->nombre ?? '—' }}</td>
                <td>{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Participantes --}}
@if($plan->participantesActivos->isNotEmpty())
<div class="seccion">
    <div class="seccion__titulo">Profesionales participantes</div>
    <div class="seccion__contenido">
        @foreach($plan->participantesActivos as $p)
        {{ $p->profesional->name }} ({{ $p->rol_en_plan }})@if(! $loop->last), @endif
        @endforeach
    </div>
</div>
@endif

{{-- Seguimiento --}}
<div class="seccion">
    <div class="seccion__titulo">Periodicidad de seguimiento</div>
    <div class="seccion__contenido">
        {{ ucfirst($plan->periodicidad_seguimiento ?? 'trimestral') }}
    </div>
</div>

{{-- Firmas --}}
<div class="firmas">
    <div class="firma-bloque">
        <div style="height: 40px;"></div>
        <div class="firma-bloque__nombre">
            {{ $plan->profesionalResponsable?->name ?? 'Profesional responsable' }}
        </div>
        <div class="firma-bloque__fecha">Trabajador/a Social de referencia</div>
        <div class="firma-bloque__fecha">Fecha: ___________</div>
    </div>
    <div class="firma-bloque">
        <div style="height: 40px;"></div>
        <div class="firma-bloque__nombre">
            {{ $plan->historia->ciudadano->nombre_completo }}
        </div>
        <div class="firma-bloque__fecha">Persona interesada</div>
        <div class="firma-bloque__fecha">Fecha: ___________</div>
    </div>
</div>

<div class="pie">
    Documento generado por VIDA360 · {{ now()->format('d/m/Y H:i') }} ·
    Historia Social #{{ $plan->historia_id }} · Versión {{ $plan->version }}
</div>

</body>
</html>
