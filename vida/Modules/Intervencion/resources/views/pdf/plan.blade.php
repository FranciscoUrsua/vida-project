<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="{{ base_path('Modules/Intervencion/resources/css/plan-pdf.css') }}">
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
            <span>{{ $ciudadano->fecha_nacimiento ? \Carbon\Carbon::parse($ciudadano->fecha_nacimiento)->format('d/m/Y') : '—' }}</span>
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
        <ul class="objetivos-lista">
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
                <th class="col-prestacion">Prestación</th>
                <th class="col-concrecion">Concreción</th>
                <th class="col-responsable">Responsable</th>
                <th class="col-inicio-corto">Inicio previsto</th>
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
                <th class="col-compromiso">Compromiso</th>
                <th class="col-recurso">Recurso relacionado</th>
                <th class="col-inicio-corto">Inicio previsto</th>
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
        <div class="firma-bloque__espacio"></div>
        <div class="firma-bloque__nombre">
            {{ $plan->profesionalResponsable?->name ?? 'Profesional responsable' }}
        </div>
        <div class="firma-bloque__fecha">Trabajador/a Social de referencia</div>
        <div class="firma-bloque__fecha">Fecha: ___________</div>
    </div>
    <div class="firma-bloque">
        <div class="firma-bloque__espacio"></div>
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
