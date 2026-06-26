@php
    use Illuminate\Support\Carbon;
@endphp

<div class="op-page">

    <div class="op-toolbar">
        <h1 class="op-toolbar__title">Recursos y plazas</h1>
    </div>

    {{-- Previsión de liberaciones (solo en pestaña pendientes) --}}
    @if($pestana === 'pendientes' && $this->previsionLiberaciones->isNotEmpty())
        <div class="alert alert-info py-2 mb-3">
            <strong>Próximas liberaciones:</strong>
            @foreach($this->previsionLiberaciones as $prox)
                {{ $prox->ciudadano?->nombre ?? '#'.$prox->ciudadano_id }}
                ({{ $prox->plaza?->nombre }}, {{ Carbon::parse($prox->fecha_fin)->format('d/m/Y') }})@if(! $loop->last), @endif
            @endforeach
        </div>
    @endif

    {{-- Pestañas --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link {{ $pestana === 'pendientes' ? 'active' : '' }}"
                    wire:click="cambiarPestana('pendientes')">
                Pendientes
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $pestana === 'activas' ? 'active' : '' }}"
                    wire:click="cambiarPestana('activas')">
                Activas
            </button>
        </li>
    </ul>

    {{-- PESTAÑA PENDIENTES --}}
    @if($pestana === 'pendientes')
        @if($this->prescripcionesPendientes->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-inbox class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay prescripciones pendientes.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Ciudadano/a</th>
                            <th>Tipo de recurso</th>
                            <th>Fecha prescripción</th>
                            <th>Posición lista</th>
                            <th>Notas TSR</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->prescripcionesPendientes as $prescripcion)
                            <tr>
                                <td>
                                    @if($prescripcion->ciudadano)
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $prescripcion->ciudadano) }}"
                                           target="_blank" class="text-decoration-none">
                                            {{ $prescripcion->ciudadano->nombre }}
                                            {{ $prescripcion->ciudadano->apellido1 }}
                                            <x-heroicon-o-arrow-top-right-on-square class="icon-12 text-muted" aria-hidden="true"/>
                                        </a>
                                    @else
                                        #{{ $prescripcion->ciudadano_id }}
                                    @endif
                                </td>
                                <td>{{ $prescripcion->tipo_destino === 'coleccion_plazas' ? 'Plaza' : 'Actividad' }}</td>
                                <td>{{ Carbon::parse($prescripcion->fecha_prescripcion)->format('d/m/Y') }}</td>
                                <td class="text-center">
                                    @if($prescripcion->listaEspera)
                                        <span class="badge bg-secondary">{{ $prescripcion->listaEspera->posicion }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $prescripcion->notas ?? '—' }}</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                                wire:click="abrirModalAsignacion({{ $prescripcion->id }})"
                                                class="btn btn-sm btn-primary">
                                            Asignar
                                        </button>
                                        <button type="button"
                                                wire:click="cancelarPrescripcion({{ $prescripcion->id }}, 'Cancelada por el centro')"
                                                class="btn btn-sm btn-outline-danger"
                                                wire:confirm="¿Cancelar esta prescripción?">
                                            Cancelar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $this->prescripcionesPendientes->links() }}
        @endif
    @endif

    {{-- PESTAÑA ACTIVAS --}}
    @if($pestana === 'activas')
        @if($this->prescripcionesActivas->isEmpty())
            <div class="op-empty">
                <x-heroicon-o-inbox class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay prescripciones activas.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Ciudadano/a</th>
                            <th>Plaza</th>
                            <th>Fecha asignación</th>
                            <th>Fecha fin prevista</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->prescripcionesActivas as $prescripcion)
                            <tr>
                                <td>
                                    @if($prescripcion->ciudadano)
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $prescripcion->ciudadano) }}"
                                           target="_blank" class="text-decoration-none">
                                            {{ $prescripcion->ciudadano->nombre }}
                                            {{ $prescripcion->ciudadano->apellido1 }}
                                            <x-heroicon-o-arrow-top-right-on-square class="icon-12 text-muted" aria-hidden="true"/>
                                        </a>
                                    @else
                                        #{{ $prescripcion->ciudadano_id }}
                                    @endif
                                </td>
                                <td>{{ $prescripcion->plaza?->nombre ?? '—' }}</td>
                                <td>{{ $prescripcion->fecha_asignacion ? Carbon::parse($prescripcion->fecha_asignacion)->format('d/m/Y') : '—' }}</td>
                                <td>{{ $prescripcion->fecha_fin ? Carbon::parse($prescripcion->fecha_fin)->format('d/m/Y') : '—' }}</td>
                                <td>
                                    <span class="badge {{ $prescripcion->estado === 'activa' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($prescripcion->estado) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        @if($prescripcion->estado === 'asignada')
                                            <button type="button"
                                                    wire:click="marcarActiva({{ $prescripcion->id }}, '{{ today()->toDateString() }}')"
                                                    class="btn btn-sm btn-outline-success">
                                                Marcar activa
                                            </button>
                                        @endif
                                        <button type="button"
                                                wire:click="marcarFinalizada({{ $prescripcion->id }})"
                                                class="btn btn-sm btn-outline-secondary"
                                                wire:confirm="¿Marcar como finalizada?">
                                            Finalizar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $this->prescripcionesActivas->links() }}
        @endif
    @endif

    {{-- Modal de asignación de plaza --}}
    @livewire('intervencion.asignar-plaza-modal')

</div>
