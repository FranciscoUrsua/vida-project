<div class="op-page">

    {{-- Indicadores de gestión --}}
    <section class="p-3" aria-labelledby="kpi-heading">
        <h2 class="h6 fw-semibold text-body-secondary mb-3" id="kpi-heading">Indicadores del centro</h2>
        <div class="row g-3">

            {{-- Ratio personas/profesional --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card border h-100">
                    <div class="card-body d-flex flex-column gap-1">
                        <span class="text-body-secondary small">Ratio personas/profesional</span>
                        <span class="display-6 fw-bold {{ $this->ratioSuperaUmbral ? 'text-warning' : '' }}"
                              aria-label="Ratio: {{ $this->ratioCarga }}">
                            {{ number_format($this->ratioCarga, 1) }}
                        </span>
                        @if($this->ratioSuperaUmbral)
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">Supera umbral</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Espera media primera cita --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card border h-100">
                    <div class="card-body d-flex flex-column gap-1">
                        <span class="text-body-secondary small">Espera media primera cita</span>
                        <span class="display-6 fw-bold">—</span>
                        <span class="text-body-secondary small">días</span>
                    </div>
                </div>
            </div>

            {{-- Profesionales sin agenda hoy --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card border h-100">
                    <div class="card-body d-flex flex-column gap-1">
                        <span class="text-body-secondary small">Profesionales sin agenda hoy</span>
                        <span class="display-6 fw-bold">—</span>
                    </div>
                </div>
            </div>

            {{-- Actividades abiertas a inscripción --}}
            <div class="col-sm-6 col-lg-3">
                <div class="card border h-100">
                    <div class="card-body d-flex flex-column gap-1">
                        <span class="text-body-secondary small">Actividades abiertas (próx. 7 días)</span>
                        <span class="display-6 fw-bold">—</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Cuadrante compacto del día --}}
    <section class="p-3 border-top" aria-labelledby="cuadrante-heading">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0 fw-semibold" id="cuadrante-heading">Cuadrante de hoy</h2>
            <a href="{{ route('supervision.cuadrante') }}" class="btn btn-sm btn-outline-secondary">
                Ver cuadrante completo
            </a>
        </div>
        <div class="p-3 bg-light border rounded text-body-secondary small text-center">
            Vista de cuadrante pendiente de integración con módulo Agenda.
        </div>
    </section>

    {{-- Aprobaciones pendientes --}}
    @if($this->aprobacionesPendientes->isNotEmpty())
    <section class="p-3 border-top" aria-labelledby="aprobaciones-heading">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0 fw-semibold" id="aprobaciones-heading">Aprobaciones pendientes</h2>
            @if($this->totalAprobacionesPendientes > 5)
                <a href="{{ route('supervision.aprobaciones') }}" class="btn btn-sm btn-outline-secondary">
                    Ver todas ({{ $this->totalAprobacionesPendientes }})
                </a>
            @endif
        </div>
        <ul class="list-group list-group-flush border rounded">
            @foreach($this->aprobacionesPendientes as $solicitud)
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <div>
                    <span class="fw-medium">{{ $solicitud->usuario?->profesional?->nombre_completo ?? $solicitud->usuario?->email }}</span>
                    <span class="text-body-secondary ms-2 small">— {{ $solicitud->rol?->name }}</span>
                </div>
                <span class="text-body-secondary small">{{ $solicitud->created_at?->diffForHumans() }}</span>
            </li>
            @endforeach
        </ul>
    </section>
    @endif

</div>
