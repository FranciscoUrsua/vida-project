{{-- Secciones Cita / Coordinación del detalle de un apunte (docs/modulo-citas.md §5). Solo en el detalle, nunca en el resumen. --}}
@if($cita = $datos['cita'] ?? null)
    <section class="border-top mt-3 pt-3" aria-labelledby="apunte-cita-titulo">
        <h3 id="apunte-cita-titulo" class="h6 fw-semibold d-flex align-items-center gap-2">
            <x-heroicon-o-calendar class="icon-16" aria-hidden="true"/> Cita
        </h3>
        <dl class="row small mb-0">
            <dt class="col-5 fw-normal text-body-secondary">Origen</dt>
            <dd class="col-7">{{ $cita['origen'] }}</dd>
            @if($cita['solicitada_por'])
                <dt class="col-5 fw-normal text-body-secondary">Solicitada por</dt>
                <dd class="col-7">{{ $cita['solicitada_por'] }} · {{ $cita['solicitada_en'] }}</dd>
            @endif
            <dt class="col-5 fw-normal text-body-secondary">Prevista</dt>
            <dd class="col-7">{{ $cita['prevista'] }}</dd>
            <dt class="col-5 fw-normal text-body-secondary">Realizada</dt>
            <dd class="col-7">{{ $cita['real'] }}@if($cita['demora_dias'] !== null) · {{ $cita['demora_dias'] }} días desde la solicitud @endif</dd>
            <dt class="col-5 fw-normal text-body-secondary">Asignación</dt>
            <dd class="col-7">{{ $cita['modo'] }}@if($cita['referencia']) (referencia: {{ $cita['referencia'] }})@endif</dd>
            <dt class="col-5 fw-normal text-body-secondary">Reprogramaciones</dt>
            <dd class="col-7">
                {{ $cita['reprogramaciones'] }}
                <a href="{{ route('agenda.citas.show', $cita['cita_id']) }}" class="ms-1">Ver historial</a>
            </dd>
            @if($cita['acompanantes'] !== [])
                <dt class="col-5 fw-normal text-body-secondary">Acompañantes</dt>
                <dd class="col-7">{{ implode(', ', $cita['acompanantes']) }}</dd>
            @endif
        </dl>
    </section>
@endif

@if($coordinacion = $datos['coordinacion'] ?? null)
    <section class="border-top mt-3 pt-3" aria-labelledby="apunte-coordinacion-titulo">
        <h3 id="apunte-coordinacion-titulo" class="h6 fw-semibold d-flex align-items-center gap-2">
            <x-heroicon-o-user-group class="icon-16" aria-hidden="true"/> Coordinación
        </h3>
        <p class="small mb-1">{{ $coordinacion['titulo'] }} · {{ $coordinacion['fecha'] }}</p>
        @if($coordinacion['convocados'] !== [])
            <p class="small text-body-secondary mb-0">Convocados: {{ implode(', ', $coordinacion['convocados']) }}</p>
        @endif
    </section>
@endif
