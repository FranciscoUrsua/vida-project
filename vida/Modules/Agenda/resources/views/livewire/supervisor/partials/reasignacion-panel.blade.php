<div class="h-100 d-flex flex-column">

    @if($this->cita === null)
        <div class="p-3 text-body-secondary small">Cita no encontrada.</div>
    @else

    {{-- Datos de la cita original --}}
    <section class="p-3 border-bottom bg-light">
        <p class="small text-body-secondary mb-1">Cita original</p>
        <p class="fw-medium mb-1">{{ $this->cita->ciudadano?->nombre_completo ?? 'Ciudadano desconocido' }}</p>
        <p class="small text-body-secondary mb-1">
            {{ substr($this->cita->hora_inicio, 0, 5) }} — {{ $this->cita->tipoSlot?->nombre ?? '—' }}
        </p>
        <p class="small text-body-secondary mb-0">
            Profesional original:
            {{ $this->cita->profesional?->profesional?->nombre_completo ?? $this->cita->profesional?->email ?? '—' }}
        </p>
    </section>

    {{-- Lista de slots disponibles --}}
    <section class="flex-grow-1 overflow-auto p-3">
        <p class="fw-semibold mb-3">Slots disponibles hoy</p>

        @if($this->slotsDisponiblesHoy->isEmpty())
            <div class="op-empty op-empty--compact">
                <x-heroicon-o-calendar-days class="op-empty__icon" aria-hidden="true"/>
                <p class="op-empty__text">No hay slots disponibles hoy para este tipo de atención.</p>
            </div>
        @else
            @foreach($this->slotsDisponiblesHoy as $slot)
            @php $esUrgencia = $slot->estado->value === 'bloqueado_urgencia'; @endphp
            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2
                        {{ $esUrgencia ? 'border-warning bg-warning-subtle' : '' }}">
                <div>
                    @if($esUrgencia)
                        <span class="badge bg-warning text-dark me-1">
                            <x-heroicon-s-bolt class="icon-12" aria-hidden="true"/> Urgencia
                        </span>
                    @endif
                    <span class="fw-medium small">{{ substr($slot->hora_inicio, 0, 5) }}</span>
                    <span class="text-body-secondary small ms-2">
                        {{ $slot->usuario?->profesional?->nombre_completo ?? $slot->usuario?->email ?? '—' }}
                    </span>
                </div>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        wire:click="confirmarReasignacion({{ $slot->id }})"
                        wire:loading.attr="disabled">
                    Asignar
                </button>
            </div>
            @endforeach
        @endif
    </section>

    {{-- Footer --}}
    <div class="p-3 border-top">
        <button type="button"
                class="btn btn-outline-secondary btn-sm w-100"
                wire:click="$parent.cerrarPanelReasignacion()">
            Cerrar sin reasignar
        </button>
    </div>

    @endif

</div>
