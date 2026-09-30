@php
    use App\Support\Ui\Tono;
    use Modules\Agenda\Enums\DestinoCita;
    use Modules\Agenda\Enums\EstadoSolicitudCita;

    $usuario = auth()->user();
    $pestanas = [
        'solicitudes' => ['Solicitudes', $this->solicitudes->count()],
        'identificar' => ['Pendientes de identificar', $this->pendientesIdentificar->count()],
        'citas' => ['Citas del centro', null],
    ];
@endphp

<div class="op-page d-flex flex-column gap-3 p-3">

    <header class="d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <p class="small text-uppercase fw-semibold text-body-secondary mb-0">Citación</p>
            <h1 class="h4 fw-bold mb-0">{{ $this->centro->nombre }}</h1>
        </div>
        <a href="{{ route('agenda.citas.nueva') }}" wire:navigate class="btn btn-primary btn-sm">
            <x-heroicon-o-plus class="icon-16 me-1" aria-hidden="true"/>
            Nueva cita
        </a>
    </header>

    <ul class="nav nav-tabs" role="tablist">
        @foreach($pestanas as $clave => [$etiqueta, $total])
            <li class="nav-item" role="presentation">
                <button type="button" role="tab" wire:click="verPestana('{{ $clave }}')"
                        @class(['nav-link', 'active' => $pestana === $clave])
                        aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}">
                    {{ $etiqueta }}
                    @if($total)
                        <span class="badge rounded-pill {{ Tono::Primario->clasesSuave() }} ms-1">{{ $total }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>

    @if($aviso)
        <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-0" role="status">
            <x-heroicon-o-check-circle class="icon-16 flex-shrink-0" aria-hidden="true"/>
            {{ $aviso }}
            <button type="button" class="btn-close btn-sm ms-auto" wire:click="$set('aviso', null)" aria-label="Cerrar aviso"></button>
        </div>
    @endif
    @error('accion')
        <div class="alert alert-danger py-2 mb-0" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Solicitudes ─────────────────────────────────────────────────── --}}
    @if($pestana === 'solicitudes')
        @if($this->solicitudes->isEmpty())
            <x-op.empty icono="inbox">No hay solicitudes pendientes en la bandeja.</x-op.empty>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach($this->solicitudes as $s)
                    @php
                        $enGestion = $s->estado === EstadoSolicitudCita::EnGestion;
                        $mia = $enGestion && $s->gestionada_por_id === $usuario->id;
                        $vencida = $s->no_despues_de->lt(today());
                    @endphp
                    <article class="card" wire:key="solicitud-{{ $s->id }}">
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge {{ $s->urgencia->tono()->clasesSuave() }}">{{ $s->urgencia->label() }}</span>
                                <span class="fw-semibold">{{ $s->ciudadano->nombre_completo }}</span>
                                @if($s->ciudadano->documentoVigente)
                                    <span class="small font-monospace text-body-secondary">{{ $s->ciudadano->documentoVigente->valor }}</span>
                                @endif
                                @if($s->ciudadano->telefono)
                                    <span class="small text-body-secondary">{{ $s->ciudadano->telefono }}</span>
                                @endif
                                <span class="small ms-auto text-body-secondary">En bandeja {{ $s->created_at->locale('es')->diffForHumans(null, true) }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-3 small mt-1">
                                {{-- Solo la etiqueta pública: nunca el nombre interno ni el motivo (§6) --}}
                                <span>{{ $s->tipoCita->etiqueta_publica }}</span>
                                <span>
                                    {{ $s->destino->label() }}
                                    @if($s->destino === DestinoCita::ProfesionalConcreto && $s->profesionalDestino)
                                        · {{ $s->profesionalDestino->nombre_completo }}
                                    @elseif($s->servicio_destino)
                                        · {{ $this->perfiles[$s->servicio_destino] ?? $s->servicio_destino }}
                                    @endif
                                </span>
                                <span @class(['text-danger fw-semibold' => $vencida])>
                                    @if($s->no_antes_de) Desde el {{ $s->no_antes_de->format('d/m/Y') }} · @endif
                                    Antes del {{ $s->no_despues_de->format('d/m/Y') }}
                                </span>
                                @if($enGestion)
                                    <span class="badge {{ $s->estado->tono()->clasesSuave() }}">{{ $mia ? 'La gestionas tú' : 'En gestión: '.$s->gestionadaPor?->nombre_completo }}</span>
                                @endif
                            </div>
                            @if($s->observaciones_citacion)
                                <p class="small text-body-secondary mb-0 mt-1">{{ $s->observaciones_citacion }}</p>
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @if(! $enGestion)
                                    <button type="button" wire:click="tomar({{ $s->id }})" class="btn btn-sm btn-primary">Tomar</button>
                                @endif
                                @if($mia || ($enGestion && $usuario->can('citas.supervisar')))
                                    <button type="button" wire:click="buscarHuecos({{ $s->id }})" class="btn btn-sm btn-primary">Buscar huecos</button>
                                    <button type="button" wire:click="prepararCierre({{ $s->id }}, 'soltar')" class="btn btn-sm btn-outline-secondary">Soltar</button>
                                    <button type="button" wire:click="prepararCierre({{ $s->id }}, 'desistir')" class="btn btn-sm btn-outline-secondary">Desistida</button>
                                @endif
                                <button type="button" wire:click="prepararCierre({{ $s->id }}, 'anular')" class="btn btn-sm btn-outline-danger">Anular</button>
                            </div>

                            @if($cierre['id'] === $s->id)
                                <form wire:submit="confirmarCierre" class="mt-2">
                                    <label for="cierre-{{ $s->id }}" class="form-label small fw-semibold mb-1">
                                        {{ $cierre['accion'] === 'soltar' ? 'Nota para quien la retome (opcional)' : 'Motivo' }}
                                    </label>
                                    <div class="d-flex gap-2">
                                        <input id="cierre-{{ $s->id }}" type="text" wire:model="cierre.texto" class="form-control form-control-sm" maxlength="1000">
                                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Confirmar</button>
                                        <button type="button" wire:click="reiniciarPaneles" class="btn btn-sm btn-outline-secondary">Volver</button>
                                    </div>
                                </form>
                            @endif

                            @if($solicitudCitando === $s->id && $propuestas !== null)
                                <div class="mt-2">
                                    @if($propuestas === [])
                                        <p class="small text-body-secondary mb-0">No hay huecos compatibles dentro del plazo. La solicitud sigue en la bandeja.</p>
                                    @else
                                        <div class="list-group">
                                            @foreach($propuestas as $p)
                                                <div class="list-group-item d-flex flex-wrap align-items-center gap-3 small" wire:key="hueco-{{ $p['slot_id'] }}">
                                                    <span class="fw-semibold">{{ $p['fecha'] }} · {{ $p['hora'] }}</span>
                                                    <span>{{ $p['profesional'] }}</span>
                                                    <span class="text-body-secondary">{{ $p['modo'] }}</span>
                                                    <button type="button" wire:click="citar({{ $p['slot_id'] }})" class="btn btn-sm btn-outline-primary ms-auto">Citar</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ── Citas externas pendientes de identificar ────────────────────── --}}
    @if($pestana === 'identificar')
        @if($this->pendientesIdentificar->isEmpty())
            <x-op.empty icono="identification">No hay citas de cita previa pendientes de identificar.</x-op.empty>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach($this->pendientesIdentificar as $c)
                    @php $datos = $c->datos_identificacion_externos ?? []; @endphp
                    <article class="card" wire:key="identificar-{{ $c->id }}">
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap align-items-center gap-3 small">
                                <span class="fw-semibold">{{ $c->fecha->format('d/m/Y') }} · {{ substr((string) $c->hora_inicio, 0, 5) }}</span>
                                <span>{{ $c->profesional?->nombre_completo }}</span>
                                <span>{{ trim(($datos['nombre'] ?? '').' '.($datos['apellidos'] ?? '')) ?: 'Sin nombre' }}</span>
                                @if($datos['numero_documento'] ?? null)
                                    <span class="font-monospace">{{ strtoupper($datos['tipo_documento'] ?? '') }} {{ $datos['numero_documento'] }}</span>
                                @endif
                                @if($datos['telefono'] ?? null)
                                    <span>{{ $datos['telefono'] }}</span>
                                @endif
                                <span class="text-body-secondary">{{ $c->referencia_externa }}</span>
                                @if($citaIdentificando !== $c->id)
                                    <button type="button" wire:click="prepararIdentificacion({{ $c->id }})" class="btn btn-sm btn-primary ms-auto">Identificar</button>
                                @endif
                            </div>
                            @if($citaIdentificando === $c->id)
                                <div class="mt-2">
                                    @include('agenda::livewire.citas.partials.buscar-persona', ['accion' => 'identificar', 'etiqueta' => 'Es esta persona'])
                                    <button type="button" wire:click="reiniciarPaneles" class="btn btn-sm btn-outline-secondary">Volver</button>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ── Citas del centro de un día: desde aquí se reprograman o cancelan ── --}}
    @if($pestana === 'citas')
        <div class="d-flex align-items-center gap-2">
            <label for="bandeja-dia" class="small fw-semibold">Día</label>
            <input id="bandeja-dia" type="date" wire:model.live="dia" class="form-control form-control-sm w-auto">
        </div>
        @if($this->citasDelDia->isEmpty())
            <x-op.empty icono="calendar">No hay citas ese día.</x-op.empty>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Hora</th>
                            <th scope="col">Persona</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Profesional</th>
                            <th scope="col">Estado</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->citasDelDia as $c)
                            <tr wire:key="cita-{{ $c->id }}">
                                <td>{{ substr((string) $c->hora_inicio, 0, 5) }}</td>
                                <td>{{ $c->ciudadano?->nombre_completo ?? 'Pendiente de identificar' }}</td>
                                <td>{{ $c->tipoCita->etiqueta_publica }}</td>
                                <td>{{ $c->profesional?->nombre_completo }}</td>
                                <td><span class="badge {{ $c->estado->tono()->clasesSuave() }}">{{ $c->estado->label() }}</span></td>
                                <td class="text-end"><a href="{{ route('agenda.citas.show', $c) }}" wire:navigate class="btn btn-sm btn-outline-primary">Abrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
