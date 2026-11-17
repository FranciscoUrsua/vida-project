{{-- Bloque «Actividades» de la ficha (fase 6, paso 4): inscripciones en sesiones e inscripción por acceso libre --}}
<div class="card mb-3" id="ficha-actividades">
    <div class="card-header d-flex align-items-center gap-2">
        <x-heroicon-o-user-group class="icon-14" aria-hidden="true"/>
        <h2 class="h6 fw-semibold mb-0">Actividades</h2>
        @if($this->puedeInscribir && ! $inscribiendo)
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" wire:click="abrirInscripcion">
                <x-heroicon-o-plus class="icon-14 me-1" aria-hidden="true"/>
                Inscribir en actividad
            </button>
        @endif
    </div>

    @if($inscribiendo)
        <div class="card-body border-bottom bg-body-tertiary">
            <p class="small text-body-secondary mb-2">
                Actividades de {{ $this->centroActivo?->nombre }} con acceso libre y plazas. Se inscribe sesión a sesión.
            </p>
            @if($this->actividadesDisponibles->isEmpty())
                <p class="small mb-2">No hay actividades con plazas libres en tu centro.</p>
            @else
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small" for="act-persona-actividad">Actividad</label>
                        <select id="act-persona-actividad" class="form-select form-select-sm" wire:model.live="actividadId">
                            <option value="">Elige una actividad…</option>
                            @foreach($this->actividadesDisponibles as $actividad)
                                <option value="{{ $actividad->id }}">{{ $actividad->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="act-persona-sesion">Sesión</label>
                        <select id="act-persona-sesion" class="form-select form-select-sm @error('sesionId') is-invalid @enderror"
                                wire:model.live="sesionId" @disabled($actividadId === null)>
                            <option value="">Elige una sesión…</option>
                            @foreach($this->sesionesDisponibles as $sesion)
                                @php $libres = $sesion->plazasLibres(\Modules\Centro\Enums\ViaInscripcion::Libre); @endphp
                                <option value="{{ $sesion->id }}">
                                    {{ $sesion->fecha->format('d/m/Y') }} · {{ substr((string) $sesion->hora_inicio, 0, 5) }}
                                    ({{ $libres === null ? 'sin límite' : $libres.' '.($libres === 1 ? 'plaza' : 'plazas') }})
                                </option>
                            @endforeach
                        </select>
                        @error('sesionId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            @endif

            @if($pideInscripcionCentro)
                <div class="alert alert-warning small mb-2" role="alert">
                    Esta actividad exige estar inscrita en {{ $this->centroActivo?->nombre }} y la persona no lo está.
                    ¿La inscribes también en el centro?
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="inscribir(true)">Inscribir en el centro y en la sesión</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('inscribiendo', false)">No inscribir</button>
                </div>
            @else
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="inscribir" @disabled($sesionId === null)>Inscribir</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('inscribiendo', false)">Cancelar</button>
                </div>
            @endif
        </div>
    @endif

    @if($this->proximas->isEmpty() && $this->anteriores->isEmpty())
        <div class="card-body">
            <x-op.empty icono="user-group">No está inscrita en ninguna actividad.</x-op.empty>
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach([['Próximas', $this->proximas], ['Anteriores', $this->anteriores]] as [$titulo, $inscripciones])
                @if($inscripciones->isNotEmpty())
                    <div class="list-group-item bg-body-tertiary small fw-semibold text-body-secondary py-1">{{ $titulo }}</div>
                    @foreach($inscripciones as $inscripcion)
                        @php $sesion = $inscripcion->sesion; @endphp
                        <div class="list-group-item" wire:key="inscripcion-{{ $inscripcion->id }}">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="small fw-semibold">
                                        {{ $sesion->actividad->nombre }}
                                        — {{ $sesion->fecha->format('d/m/Y') }} · {{ substr((string) $sesion->hora_inicio, 0, 5) }}
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ $sesion->actividad->centro?->nombre }} · {{ $inscripcion->via->label() }}
                                        @if(! $inscripcion->vigente())
                                            · Baja: {{ $inscripcion->motivo_baja }}
                                        @elseif($sesion->estado !== 'programada')
                                            · Sesión {{ $sesion->estado }}
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge {{ $inscripcion->estado->tono()->clasesSuave() }}">{{ $inscripcion->estado->label() }}</span>
                                    @if($inscripcion->vigente() && $sesion->inicio()->isFuture() && $this->puedeDarDeBaja($inscripcion))
                                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="abrirBaja({{ $inscripcion->id }})">Dar de baja</button>
                                    @endif
                                </div>
                            </div>
                            @if($bajaId === $inscripcion->id)
                                <div class="mt-2">
                                    <label class="form-label small" for="baja-inscripcion-motivo">Motivo de la baja <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-2 align-items-start">
                                        <input id="baja-inscripcion-motivo" type="text" maxlength="500"
                                               class="form-control form-control-sm @error('motivoBaja') is-invalid @enderror" wire:model="motivoBaja">
                                        <button type="button" class="btn btn-danger btn-sm text-nowrap" wire:click="confirmarBaja">Dar de baja</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('bajaId', null)">Volver</button>
                                    </div>
                                    @error('motivoBaja') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>
    @endif
</div>
