{{-- Revisión de un reparto por salida: propuesta agrupada por destino (docs/modulo-asignacion.md §5) --}}
@php
    $reparto = $this->reparto;
    $propuesto = $this->esPropuesto();
@endphp
<div class="op-page">

    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
        <a href="{{ route('supervision.asignaciones') }}" wire:navigate class="btn btn-link btn-sm px-0 d-inline-flex align-items-center gap-1">
            <x-heroicon-o-arrow-left class="icon-16" aria-hidden="true"/>
            Bandeja de asignaciones
        </a>
    </div>

    <section class="p-3">

        @if($aviso)
            <div class="alert alert-success" role="status">{{ $aviso }}</div>
        @endif
        @error('reparto')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror

        <div class="card card-body mb-3">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <h2 class="h5 mb-1">Reparto de los casos de {{ $reparto->profesionalOrigen->nombre_completo }}</h2>
                    <p class="text-body-secondary small mb-2">
                        Propuesto por {{ $reparto->iniciadoPor->nombre_completo }} el {{ $reparto->created_at->format('d/m/Y') }}
                        @if($reparto->confirmado_en)
                            · confirmado el {{ $reparto->confirmado_en->format('d/m/Y') }}
                        @endif
                    </p>
                    <p class="mb-0"><span class="text-body-secondary">Motivo:</span> {{ $reparto->motivo }}</p>
                </div>
                <span class="badge {{ $reparto->estado->tono()->clasesSuave() }}">{{ $reparto->estado->label() }}</span>
            </div>

            @if($propuesto)
                <hr>
                <p class="small text-body-secondary">
                    Cada unidad de convivencia va entera al mismo profesional. Los casos con actividad y los dormidos se reparten por separado, en proporción a la jornada.
                    Nada cambia hasta que confirmes.
                </p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirmar"
                            wire:confirm="Se cambiará el profesional de referencia de {{ $this->porDestino->sum(fn ($g) => $g['lineas']->count()) }} casos. ¿Confirmar el reparto?">
                        Confirmar reparto
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="descartar"
                            wire:confirm="¿Descartar la propuesta? Ninguna asignación cambiará.">
                        Descartar
                    </button>
                </div>
            @endif
        </div>

        @foreach($this->porDestino as $grupo)
            <div class="card mb-3" wire:key="destino-{{ $grupo['destino']->id }}">
                <div class="card-header d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="h6 fw-semibold mb-0">{{ $grupo['destino']->nombre_completo }}</h3>
                    <span class="badge rounded-pill text-bg-secondary">{{ $grupo['lineas']->count() }} casos</span>
                    <span class="small text-body-secondary">{{ $grupo['con_actividad'] }} con actividad · {{ $grupo['dormidos'] }} dormidos</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Persona</th>
                                <th scope="col">Situación</th>
                                <th scope="col">Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grupo['lineas'] as $linea)
                                <tr wire:key="linea-{{ $linea->id }}">
                                    <td>
                                        {{ $linea->historia?->ciudadano?->nombre_completo }}
                                        @if($linea->unidad_convivencia_id)
                                            <span class="badge bg-primary-subtle text-primary-emphasis ms-1">Unidad de convivencia</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $linea->con_actividad ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                                            {{ $linea->con_actividad ? 'Con actividad' : 'Dormido' }}
                                        </span>
                                        @if($linea->modificada_por_supervisor)
                                            <span class="badge bg-info-subtle text-info-emphasis ms-1">Cambiado a mano</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($propuesto)
                                            <label for="destino-{{ $linea->id }}" class="visually-hidden">Destino de {{ $linea->historia?->ciudadano?->nombre_completo }}</label>
                                            <select id="destino-{{ $linea->id }}" class="form-select form-select-sm"
                                                    wire:change="cambiarDestino({{ $linea->id }}, $event.target.value)">
                                                @foreach($this->destinosPosibles as $opcion)
                                                    <option value="{{ $opcion->id }}" @selected($opcion->id === $linea->profesional_destino_id)>{{ $opcion->nombre_completo }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $grupo['destino']->nombre_completo }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

    </section>

</div>
