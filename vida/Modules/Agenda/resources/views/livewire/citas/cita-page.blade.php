@php
    $usuario = auth()->user();
    $solicitud = $cita->solicitud;
    $ciudadano = $cita->ciudadano;
    $pedidoPor = [
        'ciudadano' => 'La persona citada',
        'centro' => 'El centro',
        'profesional' => 'El profesional',
    ];
@endphp

<div class="op-page d-flex flex-column gap-3 p-3">

    <header class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <p class="small text-uppercase fw-semibold text-body-secondary mb-0">Cita</p>
            <h1 class="h4 fw-bold mb-1">
                {{ $cita->fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }} · {{ substr((string) $cita->hora_inicio, 0, 5) }}
            </h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge {{ $cita->estado->tono()->clasesSuave() }}">{{ $cita->estado->label() }}</span>
                @if($cita->pendiente_cierre)
                    <span class="badge {{ \App\Support\Ui\Tono::Aviso->clasesSuave() }}">Pendiente de cierre</span>
                @endif
                @if($cita->pendienteDeIdentificar())
                    <span class="badge {{ \App\Support\Ui\Tono::Aviso->clasesSuave() }}">Pendiente de identificar</span>
                @endif
                <span class="small text-body-secondary">{{ $cita->tipoCita->nombreParaUsuario($usuario) }} · {{ $cita->modalidad->label() }}</span>
            </div>
        </div>

        @if($this->puedeReprogramar || $this->puedeCancelar)
            <div class="d-flex gap-2">
                @if($this->puedeReprogramar)
                    <button type="button" wire:click="abrir('reprogramar')" class="btn btn-outline-primary btn-sm">Reprogramar</button>
                @endif
                @if($this->puedeCancelar)
                    <button type="button" wire:click="abrir('cancelar')" class="btn btn-outline-danger btn-sm">Cancelar cita</button>
                @endif
            </div>
        @endif
    </header>

    @if($aviso)
        <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-0" role="status">
            <x-heroicon-o-check-circle class="icon-16 flex-shrink-0" aria-hidden="true"/>
            {{ $aviso }}
        </div>
    @endif

    {{-- Reprogramar: la persona elige entre los huecos propuestos --}}
    @if($accion === 'reprogramar')
        <section class="card card-body" aria-labelledby="titulo-reprogramar">
            <h2 id="titulo-reprogramar" class="h6 fw-bold mb-3">Reprogramar la cita</h2>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label for="rep-alcance" class="form-label small fw-semibold mb-1">Buscar huecos de</label>
                    <select id="rep-alcance" wire:model="formReprogramar.alcance" class="form-select form-select-sm">
                        <option value="mismo">El mismo profesional</option>
                        <option value="perfil">Cualquier profesional de su perfil</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="rep-hasta" class="form-label small fw-semibold mb-1">Hasta (opcional)</label>
                    <input id="rep-hasta" type="date" wire:model="formReprogramar.hasta" class="form-control form-control-sm">
                    @error('formReprogramar.hasta') <div class="small text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="rep-pedido" class="form-label small fw-semibold mb-1">A petición de</label>
                    <select id="rep-pedido" wire:model="formReprogramar.pedido_por" class="form-select form-select-sm">
                        @foreach($pedidoPor as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label for="rep-motivo" class="form-label small fw-semibold mb-1">Motivo</label>
                    <input id="rep-motivo" type="text" wire:model="formReprogramar.motivo" class="form-control form-control-sm" maxlength="1000">
                    @error('formReprogramar.motivo') <div class="small text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="d-flex gap-2 mb-3">
                <button type="button" wire:click="buscarHuecos" class="btn btn-primary btn-sm">Buscar huecos</button>
                <button type="button" wire:click="abrir(null)" class="btn btn-outline-secondary btn-sm">Cerrar</button>
            </div>
            @error('reprogramar') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror

            @if($propuestas !== null)
                @if($propuestas === [])
                    <x-op.empty icono="calendar">No hay huecos compatibles en ese periodo.</x-op.empty>
                @else
                    <div class="list-group">
                        @foreach($propuestas as $p)
                            <div class="list-group-item d-flex flex-wrap align-items-center gap-3" wire:key="rep-{{ $p['slot_id'] }}">
                                <span class="fw-semibold">{{ $p['fecha'] }} · {{ $p['hora'] }}</span>
                                <span class="small">{{ $p['profesional'] }}</span>
                                <button type="button" wire:click="reprogramar({{ $p['slot_id'] }})" class="btn btn-sm btn-outline-primary ms-auto">Mover aquí</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>
    @endif

    {{-- Cancelar: quien cancela decide si se abre una solicitud nueva --}}
    @if($accion === 'cancelar')
        <section class="card card-body" aria-labelledby="titulo-cancelar">
            <h2 id="titulo-cancelar" class="h6 fw-bold mb-3">Cancelar la cita</h2>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label for="can-pedido" class="form-label small fw-semibold mb-1">A petición de</label>
                    <select id="can-pedido" wire:model="formCancelar.pedido_por" class="form-select form-select-sm">
                        @foreach($pedidoPor as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label for="can-motivo" class="form-label small fw-semibold mb-1">Motivo</label>
                    <input id="can-motivo" type="text" wire:model="formCancelar.motivo" class="form-control form-control-sm" maxlength="1000">
                    @error('formCancelar.motivo') <div class="small text-danger">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="can-abrir" wire:model="formCancelar.abrir_solicitud">
                <label class="form-check-label small" for="can-abrir">Abrir una solicitud nueva en la bandeja de citación</label>
            </div>
            @error('cancelar') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
            <div class="d-flex gap-2">
                <button type="button" wire:click="cancelar" class="btn btn-danger btn-sm">Cancelar la cita</button>
                <button type="button" wire:click="abrir(null)" class="btn btn-outline-secondary btn-sm">Volver</button>
            </div>
        </section>
    @endif

    <div class="row g-3">
        <section class="col-lg-5" aria-labelledby="titulo-datos">
            <div class="card card-body h-100">
                <h2 id="titulo-datos" class="h6 fw-bold mb-3">Datos</h2>
                <dl class="row small mb-0">
                    <dt class="col-5 fw-normal text-body-secondary">Persona</dt>
                    <dd class="col-7">{{ $ciudadano?->nombre_completo ?? 'Pendiente de identificar' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">Profesional</dt>
                    <dd class="col-7">{{ $cita->profesional?->nombre_completo ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">Origen</dt>
                    <dd class="col-7">{{ $cita->origen->label() }}@if($cita->referencia_externa) · {{ $cita->referencia_externa }}@endif</dd>
                    <dt class="col-5 fw-normal text-body-secondary">Asignación</dt>
                    <dd class="col-7">{{ $cita->modo_asignacion->label() }}</dd>
                    @if($solicitud)
                        <dt class="col-5 fw-normal text-body-secondary">Urgencia</dt>
                        <dd class="col-7"><span class="badge {{ $solicitud->urgencia->tono()->clasesSuave() }}">{{ $solicitud->urgencia->label() }}</span></dd>
                        <dt class="col-5 fw-normal text-body-secondary">Solicitada</dt>
                        <dd class="col-7">{{ $solicitud->created_at->format('d/m/Y') }} · {{ $solicitud->canal->label() }}</dd>
                        @if($solicitud->observaciones_citacion)
                            <dt class="col-5 fw-normal text-body-secondary">Observaciones</dt>
                            <dd class="col-7">{{ $solicitud->observaciones_citacion }}</dd>
                        @endif
                        @if($motivo = $solicitud->motivoVisiblePara($usuario))
                            <dt class="col-5 fw-normal text-body-secondary">Motivo</dt>
                            <dd class="col-7">{{ $motivo }}</dd>
                        @endif
                    @endif
                    @if($cita->estado === \Modules\Agenda\Enums\EstadoCita::Cancelada)
                        <dt class="col-5 fw-normal text-body-secondary">Cancelación</dt>
                        <dd class="col-7">{{ $cita->motivo_cancelacion }}@if($cita->pedido_por_cancelacion) · {{ $cita->pedido_por_cancelacion->label() }}@endif</dd>
                    @endif
                    @if($cita->acompanantes->isNotEmpty())
                        <dt class="col-5 fw-normal text-body-secondary">Acompañantes</dt>
                        <dd class="col-7">{{ $cita->acompanantes->map->nombreVisible()->implode(', ') }}</dd>
                    @endif
                </dl>
            </div>
        </section>

        <section class="col-lg-7" aria-labelledby="titulo-cadena">
            <div class="card card-body h-100">
                <h2 id="titulo-cadena" class="h6 fw-bold mb-3">Reprogramaciones</h2>
                @if($this->cadena->count() === 1)
                    <p class="small text-body-secondary mb-0">La cita no se ha movido.</p>
                @else
                    <ol class="list-group list-group-numbered small">
                        @foreach($this->cadena as $eslabon)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2" wire:key="cadena-{{ $eslabon->id }}">
                                @if($eslabon->id === $cita->id)
                                    <span class="fw-semibold">{{ $eslabon->fecha->format('d/m/Y') }} {{ substr((string) $eslabon->hora_inicio, 0, 5) }}</span>
                                @else
                                    <a href="{{ route('agenda.citas.show', $eslabon) }}" wire:navigate>{{ $eslabon->fecha->format('d/m/Y') }} {{ substr((string) $eslabon->hora_inicio, 0, 5) }}</a>
                                @endif
                                <span class="text-body-secondary">{{ $eslabon->profesional?->nombre_completo }}</span>
                                <span class="badge {{ $eslabon->estado->tono()->clasesSuave() }} ms-auto">{{ $eslabon->estado->label() }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    </div>

    <section class="card" aria-labelledby="titulo-historial">
        <div class="card-body">
            <h2 id="titulo-historial" class="h6 fw-bold mb-3">Historial</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Acción</th>
                            <th scope="col">Quién</th>
                            <th scope="col">A petición de</th>
                            <th scope="col">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->eventos as $evento)
                            <tr wire:key="evento-{{ $evento->id }}">
                                <td class="text-nowrap">{{ $evento->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $evento->accion->label() }}</td>
                                <td>{{ $evento->actor?->nombre_completo ?? $evento->actor_tipo->label() }}</td>
                                <td>{{ $evento->pedido_por?->label() ?? '—' }}</td>
                                <td>{{ $evento->motivo ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-body-tertiary mt-2 mb-0">El historial es inmutable: forma parte del expediente de la persona.</p>
        </div>
    </section>
</div>
