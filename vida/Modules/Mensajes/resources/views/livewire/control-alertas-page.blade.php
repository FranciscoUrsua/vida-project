{{-- Control de alertas del supervisor: escaladas, aviso al equipo y seguimiento --}}
@php
    $estados = [
        'pendiente' => ['Pendiente', 'bg-warning-subtle text-warning-emphasis'],
        'reconocida' => ['Atendida', 'bg-success-subtle text-success-emphasis'],
        'escalada' => ['Escalada', 'bg-danger-subtle text-danger-emphasis'],
        'vencida' => ['Vencida', 'bg-secondary-subtle text-secondary-emphasis'],
    ];
@endphp
<div class="op-page">
    <div class="row g-3 p-3">

        {{-- Escaladas al supervisor --}}
        <section class="col-12 col-xl-7" aria-labelledby="titulo-escaladas">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 id="titulo-escaladas" class="h6 fw-semibold mb-0">Alertas escaladas a ti</h2>
                    @if($this->escaladas->isNotEmpty())
                        <span class="badge rounded-pill text-bg-danger">{{ $this->escaladas->count() }}</span>
                    @endif
                </div>

                @if($this->escaladas->isEmpty())
                    <div class="op-empty">
                        <x-heroicon-o-check-circle class="op-empty__icon" aria-hidden="true"/>
                        <p class="op-empty__text">No tienes alertas escaladas.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($this->escaladas as $parte)
                            <li class="list-group-item py-3" wire:key="escalada-{{ $parte->id }}">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $parte->alerta->titulo }}</div>
                                        <p class="mb-1">{{ $parte->alerta->cuerpo }}</p>
                                        <small class="text-body-secondary">
                                            Sin reconocer por {{ $parte->usuario->nombre_completo }}
                                            · escalada el {{ $parte->escalada_en?->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                                        @if($cierreConfirmandoId === $parte->id)
                                            <span class="small text-body-secondary">¿Cerrar esta alerta?</span>
                                            <div class="d-flex gap-2">
                                                <button type="button" wire:click="cancelarCierre" class="btn btn-sm btn-outline-secondary">Cancelar</button>
                                                <button type="button" wire:click="cerrar" class="btn btn-sm btn-primary">Sí, cerrar</button>
                                            </div>
                                        @else
                                            <button type="button" wire:click="confirmarCierre({{ $parte->id }})" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                                <x-heroicon-o-check class="icon-14" aria-hidden="true"/> Cerrar alerta
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Aviso al equipo --}}
        <section class="col-12 col-xl-5" aria-labelledby="titulo-aviso">
            <div class="card h-100">
                <div class="card-header">
                    <h2 id="titulo-aviso" class="h6 fw-semibold mb-0">Enviar aviso al equipo</h2>
                </div>
                <div class="card-body">
                    <livewire:mensajes-nuevo-aviso-supervisor :key="'nuevo-aviso'" />
                </div>
            </div>
        </section>

        {{-- Seguimiento de las alertas del equipo --}}
        <section class="col-12" aria-labelledby="titulo-equipo">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h2 id="titulo-equipo" class="h6 fw-semibold mb-0">Alertas y avisos de tu equipo</h2>
                    <select wire:model.live="filtro" class="form-select form-select-sm w-auto ms-auto" aria-label="Filtrar alertas del equipo">
                        <option value="abiertas">Sin atender por todos</option>
                        <option value="recientes">Últimos 30 días</option>
                    </select>
                </div>

                @if($this->alertasEquipo->isEmpty())
                    <div class="op-empty">
                        <x-heroicon-o-bell class="op-empty__icon" aria-hidden="true"/>
                        <p class="op-empty__text">No hay alertas ni avisos que mostrar.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Alerta o aviso</th>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Atendidas</th>
                                    <th scope="col">Personas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->alertasEquipo as $alerta)
                                    @php
                                        $total = $alerta->destinatarios->count();
                                        $atendidas = $alerta->destinatarios->where('estado', \Modules\Mensajes\Enums\EstadoAlerta::Reconocida)->count();
                                    @endphp
                                    <tr wire:key="equipo-{{ $alerta->id }}">
                                        <td>
                                            <span class="badge rounded-pill {{ $alerta->tipo->value === 'alerta' ? 'text-bg-danger' : 'text-bg-secondary' }} me-1">
                                                {{ $alerta->tipo->value === 'alerta' ? 'Alerta' : 'Aviso' }}
                                            </span>
                                            {{ $alerta->titulo }}
                                        </td>
                                        <td class="text-nowrap">{{ $alerta->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="text-nowrap">{{ $atendidas }} de {{ $total }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($alerta->destinatarios as $d)
                                                    @php [$etiqueta, $clases] = $estados[$d->estado->value]; @endphp
                                                    <span class="badge rounded-pill {{ $clases }}">
                                                        {{ $d->usuario?->nombre_completo }} · {{ $etiqueta }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

    </div>
</div>
