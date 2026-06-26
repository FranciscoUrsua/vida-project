{{-- Modal de asignación de plaza concreta a una prescripción --}}
<div>
@if($abierto && $this->prescripcion)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
     aria-labelledby="asignar-plaza-titulo">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="asignar-plaza-titulo">Asignar plaza</h5>
                <button type="button" class="btn-close" wire:click="cancelar" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                {{-- Datos del ciudadano --}}
                @php $ciudadano = $this->prescripcion->ciudadano; @endphp
                <div class="mb-3 p-3 bg-light rounded">
                    <div class="fw-semibold">
                        {{ $ciudadano?->nombre }} {{ $ciudadano?->apellido1 }} {{ $ciudadano?->apellido2 }}
                    </div>
                    @if($ciudadano)
                        <a href="{{ route('ciudadania.ciudadano.ficha', $ciudadano) }}"
                           target="_blank"
                           class="btn btn-sm btn-outline-secondary mt-1">
                            <x-heroicon-o-arrow-top-right-on-square class="icon-14" aria-hidden="true"/>
                            Ver ficha completa
                        </a>
                    @endif
                    @if($this->prescripcion->notas)
                        <div class="mt-2 text-muted small">
                            <strong>Notas del TSR:</strong> {{ $this->prescripcion->notas }}
                        </div>
                    @endif
                </div>

                {{-- Inventario de plazas --}}
                @if($this->plazasDisponibles->isEmpty())
                    <div class="alert alert-warning">No hay plazas configuradas en este centro.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Plaza</th>
                                    <th>Espacio / Tipo</th>
                                    <th>Estado</th>
                                    <th>Liberación prevista</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->plazasDisponibles as $plaza)
                                    @php
                                        $esLibre = $plaza->estado === 'libre';
                                        $prescripcionActiva = $esLibre ? null : $plaza->prescripcion;
                                    @endphp
                                    <tr>
                                        <td>{{ $plaza->nombre }}</td>
                                        <td class="text-muted small">{{ $plaza->espacio?->nombre ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $esLibre ? 'bg-success' : ($plaza->estado === 'mantenimiento' ? 'bg-danger' : 'bg-warning text-dark') }}">
                                                {{ ucfirst($plaza->estado) }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            @if(! $esLibre && $prescripcionActiva?->fecha_fin)
                                                {{ \Carbon\Carbon::parse($prescripcionActiva->fecha_fin)->format('d/m/Y') }} (estimado)
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($esLibre)
                                                <button type="button"
                                                        wire:click="asignar({{ $plaza->id }})"
                                                        class="btn btn-sm btn-primary">
                                                    Asignar
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @error('plaza')
                    <div class="alert alert-danger py-2 mt-2">{{ $message }}</div>
                @enderror

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="cancelar">Cerrar</button>
            </div>

        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif
</div>
