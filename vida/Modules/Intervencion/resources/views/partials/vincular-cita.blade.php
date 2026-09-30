{{-- Propuesta de vinculación del apunte con la cita de hoy o pendiente de cierre (docs/modulo-citas.md §3.5.3), marcada por defecto --}}
@if($cita = $this->citaVinculable)
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="vincular-cita" wire:model="vincularCita">
        <label class="form-check-label small" for="vincular-cita">
            Vincular a la cita de las {{ substr((string) $cita->hora_inicio, 0, 5) }}@unless($cita->fecha->isToday()) del {{ $cita->fecha->format('d/m/Y') }}@endunless
        </label>
    </div>
@endif
