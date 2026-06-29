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

        @if($this->cuadranteDeHoy->isEmpty())
            <div class="op-empty py-3">
                <x-heroicon-o-calendar class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay slots generados para hoy.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Profesional</th>
                            <th>Horario</th>
                            <th class="text-center">Slots</th>
                            <th class="text-center">Disponibles</th>
                            <th class="text-center">Reservados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->cuadranteDeHoy as $fila)
                        <tr>
                            <td class="fw-medium">{{ $fila['nombre'] }}</td>
                            <td class="text-body-secondary">{{ $fila['inicio'] }} – {{ $fila['fin'] }}</td>
                            <td class="text-center">{{ $fila['total'] }}</td>
                            <td class="text-center">
                                @if($fila['disponibles'] > 0)
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ $fila['disponibles'] }}</span>
                                @else
                                    <span class="text-body-tertiary">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($fila['reservados'] > 0)
                                    <span class="badge bg-primary-subtle text-primary-emphasis">{{ $fila['reservados'] }}</span>
                                @else
                                    <span class="text-body-tertiary">0</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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
