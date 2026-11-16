{{-- Historial de citas de la persona, de cualquier centro (docs/modulo-citas.md §6 bis) --}}
{{-- Nunca el motivo de la solicitud ni la intervención vinculada --}}
<div class="card mb-3" id="ficha-citas">
    <div class="card-header d-flex align-items-center gap-2">
        <x-heroicon-o-calendar-days class="icon-14" aria-hidden="true"/>
        <h2 class="h6 fw-semibold mb-0">Citas</h2>
    </div>

    @php
        $futuras = $this->futuras;
        $pasadas = $this->pasadas;
    @endphp

    @if($futuras->isEmpty() && $pasadas->isEmpty())
        <div class="card-body">
            <x-op.empty icono="calendar-days">No tiene citas.</x-op.empty>
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach([['Programadas', $futuras], ['Pasadas', $pasadas]] as [$titulo, $grupos])
                @if($grupos->isNotEmpty())
                    <div class="list-group-item bg-body-tertiary small fw-semibold text-body-secondary py-1">{{ $titulo }}</div>
                    @foreach($grupos as $grupo)
                        @php $cita = $grupo['cita']; @endphp
                        <div class="list-group-item" wire:key="cita-{{ $cita->id }}">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="small fw-semibold">
                                        {{ $cita->fecha->format('d/m/Y') }} · {{ substr((string) $cita->hora_inicio, 0, 5) }}
                                        — {{ $cita->tipoCita->nombreParaUsuario($usuario) }}
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ $cita->centro?->nombre }} · {{ $cita->profesional?->nombreVisible() }} · {{ $cita->modalidad->label() }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge {{ $cita->estado->tono()->clasesSuave() }}">{{ $cita->estado->label() }}</span>
                                    @if($this->gestionable($cita))
                                        <a href="{{ route('agenda.citas.show', $cita) }}" wire:navigate class="btn btn-outline-primary btn-sm">
                                            Reprogramar o cancelar
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if($grupo['anteriores']->isNotEmpty())
                                <ul class="list-unstyled small text-body-secondary mt-1 mb-0 ps-3 border-start">
                                    @foreach($grupo['anteriores'] as $anterior)
                                        <li wire:key="cita-{{ $cita->id }}-anterior-{{ $anterior->id }}">
                                            Reprogramada desde el {{ $anterior->fecha->format('d/m/Y') }} · {{ substr((string) $anterior->hora_inicio, 0, 5) }}
                                            ({{ $anterior->centro?->nombre }})
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>

        @if($this->pasadasOcultas > 0)
            <div class="card-footer">
                <button type="button" wire:click="verMas" class="btn btn-link btn-sm p-0">
                    Ver más ({{ $this->pasadasOcultas }})
                </button>
            </div>
        @endif
    @endif
</div>
