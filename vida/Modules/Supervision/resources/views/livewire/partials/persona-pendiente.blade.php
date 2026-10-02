{{-- Persona de una entrada de la bandeja de asignaciones. Parámetro: $pendiente (AsignacionPendiente).
     Un colectivo protegido que la policy no deja ver al supervisor sale sin nombre ni enlace (CLAUDE.md §3). --}}
@if($pendiente->ciudadano !== null && ($pendiente->ciudadano->colectivo_extra_protegido === false || auth()->user()->can('view', $pendiente->ciudadano)))
    <a href="{{ route('ciudadania.ciudadano.ficha', $pendiente->ciudadano_id) }}" wire:navigate class="fw-medium">{{ $pendiente->ciudadano->nombre_completo }}</a>
@else
    <span class="fw-medium text-body-secondary">Persona con protección especial</span>
@endif
