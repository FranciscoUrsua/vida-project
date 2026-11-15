{{-- Bandeja de tramitación (fase 3 bis, paso 5.3) --}}
@php
    $pestanas = ['pendientes' => 'Pendientes de tomar', 'preparacion' => 'En preparación', 'incidencias' => 'Con incidencia abierta', 'enviadas' => 'Enviadas'];
@endphp
<div class="op-page">
    <ul class="nav nav-tabs px-3 pt-2">
        @foreach($pestanas as $clave => $etiqueta)
            <li class="nav-item">
                <button type="button" wire:click="verPestana('{{ $clave }}')"
                        class="nav-link {{ $pestana === $clave ? 'active' : '' }}"
                        @if($pestana === $clave) aria-current="page" @endif>
                    {{ $etiqueta }} <span class="badge text-bg-light border">{{ $this->contadores[$clave] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <section class="p-3">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <div>
                <label for="filtro-prestacion" class="form-label small fw-semibold mb-1">Prestación</label>
                <select id="filtro-prestacion" wire:model.live="prestacionId" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($this->prestaciones as $prestacion)
                        <option value="{{ $prestacion->id }}">{{ $prestacion->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtro-antiguedad" class="form-label small fw-semibold mb-1">Sin movimiento desde hace</label>
                <select id="filtro-antiguedad" wire:model.live="antiguedad" class="form-select form-select-sm">
                    <option value="0">Cualquier antigüedad</option>
                    <option value="7">Más de 7 días</option>
                    <option value="15">Más de 15 días</option>
                    <option value="30">Más de 30 días</option>
                </select>
            </div>
            @if($pestana === 'preparacion')
                <div class="form-check ms-2 mb-1">
                    <input class="form-check-input" type="checkbox" id="solo-mias" wire:model.live="soloMias">
                    <label class="form-check-label small" for="solo-mias">Solo las mías</label>
                </div>
            @endif
        </div>

        @if($this->solicitudes->isEmpty())
            <x-op.empty icono="inbox">No hay solicitudes en esta bandeja.</x-op.empty>
        @else
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <caption class="visually-hidden">{{ $pestanas[$pestana] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Persona</th>
                                <th scope="col">Prestación</th>
                                <th scope="col">Centro</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Responsable</th>
                                <th scope="col">Último movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->solicitudes as $solicitud)
                                <tr wire:key="sol-{{ $solicitud->id }}">
                                    <td><a href="{{ route('prestaciones.solicitud', $solicitud) }}" wire:navigate>{{ $solicitud->ciudadano?->nombre_completo }}</a></td>
                                    <td>{{ $solicitud->prestacion->nombre }}</td>
                                    <td class="text-body-secondary">{{ $solicitud->centroSolicitud?->nombre_corto ?? $solicitud->centroSolicitud?->nombre }}</td>
                                    <td>
                                        <span class="badge rounded-pill {{ $solicitud->estado->tono()->clasesSuave() }}">{{ $solicitud->estado->label() }}</span>
                                        @if($solicitud->estado_externo_texto)
                                            <span class="small text-body-secondary d-block">{{ $solicitud->estado_externo_texto }}</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $solicitud->responsable?->nombreVisible() ?? '—' }}
                                        @if($solicitud->sugerida && ! $solicitud->responsable)
                                            <span class="text-body-secondary d-block">Sugerida: {{ $solicitud->sugerida->nombreVisible() }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-body-secondary text-nowrap">{{ $solicitud->updated_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
</div>
