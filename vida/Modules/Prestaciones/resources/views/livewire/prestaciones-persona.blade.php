<div>
@if($visible)
    <section class="card card-body mb-3" aria-labelledby="titulo-prestaciones-persona">
        <h2 id="titulo-prestaciones-persona" class="h6 fw-semibold d-flex align-items-center gap-2 mb-2">
            <x-heroicon-o-gift class="icon-16" aria-hidden="true"/>
            Prestaciones
        </h2>
        @error('accion') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror

        @forelse($this->prestaciones as $p)
            <div class="border-top py-2 small" wire:key="pp-{{ $p->id }}">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold">{{ $p->prestacion->nombre }}</span>
                    <span class="badge rounded-pill {{ $p->estado->tono()->clasesSuave() }} ms-auto">{{ $p->estado->label() }}</span>
                </div>
                <div class="text-body-secondary">
                    @if($p->estado === \Modules\Prestaciones\Enums\EstadoPrestacionPersona::Concedida)
                        Concedida el {{ $p->fecha_concesion?->format('d/m/Y') }} · {{ (int) $p->fecha_concesion?->diffInDays(today()) }} días sin empezar
                    @elseif($p->fecha_inicio)
                        Desde el {{ $p->fecha_inicio->format('d/m/Y') }}
                    @endif
                    @if($p->condiciones['intensidad'] ?? null) · {{ $p->condiciones['intensidad'] }} @endif
                    @if($p->motivo_suspension) · {{ $p->motivo_suspension }} @endif
                </div>
                @if($this->puedeGestionar($p))
                    <div class="d-flex gap-2 mt-1">
                        @if($p->estado === \Modules\Prestaciones\Enums\EstadoPrestacionPersona::Concedida)
                            <button type="button" wire:click="preparar({{ $p->id }}, 'inicio')" class="btn btn-link btn-sm p-0">Registrar inicio</button>
                        @endif
                        @if($p->estado === \Modules\Prestaciones\Enums\EstadoPrestacionPersona::Activa)
                            <button type="button" wire:click="preparar({{ $p->id }}, 'suspender')" class="btn btn-link btn-sm p-0">Suspender</button>
                        @endif
                        @if($p->estado === \Modules\Prestaciones\Enums\EstadoPrestacionPersona::Suspendida)
                            <button type="button" wire:click="preparar({{ $p->id }}, 'reanudar')" class="btn btn-link btn-sm p-0">Reanudar</button>
                        @endif
                        <button type="button" wire:click="preparar({{ $p->id }}, 'finalizar')" class="btn btn-link btn-sm p-0">Finalizar</button>
                    </div>
                @endif
                @if($accionId === $p->id)
                    <div class="mt-2">
                        @if(in_array($accion, ['inicio', 'finalizar'], true))
                            <label for="pp-fecha-{{ $p->id }}" class="form-label mb-1">{{ $accion === 'inicio' ? 'Fecha de inicio efectivo' : 'Fecha de fin' }}</label>
                            <input id="pp-fecha-{{ $p->id }}" type="date" wire:model="fecha" class="form-control form-control-sm w-auto mb-1">
                        @endif
                        @if(in_array($accion, ['suspender', 'finalizar'], true))
                            <label for="pp-motivo-{{ $p->id }}" class="form-label mb-1">Motivo</label>
                            <input id="pp-motivo-{{ $p->id }}" type="text" wire:model="motivo" class="form-control form-control-sm mb-1">
                        @endif
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="confirmar" class="btn btn-primary btn-sm">Confirmar</button>
                            <button type="button" wire:click="$set('accionId', null)" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-body-secondary small mb-0">Sin prestaciones vigentes.</p>
        @endforelse

        @if($this->solicitudes->isNotEmpty())
            <h3 class="small fw-semibold text-body-secondary mt-3 mb-1">Solicitudes en curso</h3>
            @foreach($this->solicitudes as $s)
                <div class="d-flex align-items-center gap-2 small py-1" wire:key="sol-{{ $s->id }}">
                    <a href="{{ route('prestaciones.solicitud', $s) }}" wire:navigate>{{ $s->prestacion->nombre }}</a>
                    <span class="badge rounded-pill {{ $s->estado->tono()->clasesSuave() }} ms-auto">{{ $s->estado->label() }}</span>
                </div>
            @endforeach
        @endif
    </section>
@endif
</div>
