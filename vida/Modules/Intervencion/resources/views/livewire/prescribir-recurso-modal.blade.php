{{-- Modal de prescripción de recurso en tres pasos --}}
<div>
@if($abierto)
<div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
     aria-labelledby="prescribir-recurso-titulo">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            {{-- Cabecera --}}
            <div class="modal-header">
                <h5 class="modal-title" id="prescribir-recurso-titulo">Prescribir recurso</h5>
                <button type="button" class="btn-close" wire:click="cancelar" aria-label="Cerrar"></button>
            </div>

            {{-- Indicador de pasos --}}
            <div class="modal-body pb-0">
                <nav class="nav nav-pills nav-fill mb-3" aria-label="Pasos de prescripción">
                    <span class="nav-link {{ $paso === 1 ? 'active' : 'disabled' }}">1. Tipo de recurso</span>
                    <span class="nav-link {{ $paso === 2 ? 'active' : ($paso > 2 ? '' : 'disabled') }}">2. Destino</span>
                    <span class="nav-link {{ $paso === 3 ? 'active' : 'disabled' }}">3. Confirmación</span>
                </nav>
            </div>

            {{-- Cuerpo --}}
            <div class="modal-body">

                {{-- PASO 1 — Tipo de recurso --}}
                @if($paso === 1)
                    <div wire:key="paso-1">
                        <p class="text-muted small mb-3">Selecciona el tipo de recurso que necesita el ciudadano.</p>
                        @if($this->tiposRecurso->isEmpty())
                            <div class="alert alert-warning">No hay recursos disponibles configurados en el sistema.</div>
                        @else
                            <div class="list-group">
                                @foreach($this->tiposRecurso as $tipo)
                                    <button type="button"
                                            wire:click="$set('tipoRecurso', '{{ $tipo }}')"
                                            class="list-group-item list-group-item-action {{ $tipoRecurso === $tipo ? 'active' : '' }}">
                                        {{ ucfirst($tipo) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- PASO 2 — Destino --}}
                @if($paso === 2)
                    <div wire:key="paso-2">
                        <p class="text-muted small mb-3">Selecciona el centro o red de destino.</p>
                        @if($this->opcionesDestino->isEmpty())
                            <div class="alert alert-info">No hay centros disponibles para este tipo de recurso.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th class="text-center">Disponibles</th>
                                            <th class="text-center">En espera</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->opcionesDestino as $opcion)
                                            <tr class="{{ $destinoId === $opcion->id ? 'table-primary' : '' }}">
                                                <td>{{ $opcion->nombre }}</td>
                                                <td class="text-center">
                                                    {{ method_exists($opcion, 'plazasDisponibles') ? $opcion->plazasDisponibles() : '—' }}
                                                </td>
                                                <td class="text-center text-muted small">—</td>
                                                <td>
                                                    <button type="button"
                                                            wire:click="$set('destinoId', {{ $opcion->id }})"
                                                            class="btn btn-sm {{ $destinoId === $opcion->id ? 'btn-primary' : 'btn-outline-primary' }}">
                                                        {{ $destinoId === $opcion->id ? 'Seleccionado' : 'Seleccionar' }}
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- PASO 3 — Confirmación --}}
                @if($paso === 3)
                    <div wire:key="paso-3">
                        <dl class="row mb-3">
                            <dt class="col-sm-4">Tipo de recurso</dt>
                            <dd class="col-sm-8">{{ ucfirst($tipoRecurso ?? '—') }}</dd>
                            <dt class="col-sm-4">Destino</dt>
                            <dd class="col-sm-8">
                                @php
                                    $nombreDestino = $this->opcionesDestino->firstWhere('id', $destinoId)?->nombre ?? '—';
                                @endphp
                                {{ $nombreDestino }}
                            </dd>
                            <dt class="col-sm-4">Disponibilidad</dt>
                            <dd class="col-sm-8">
                                @if($this->hayPlazaDisponible)
                                    <span class="badge bg-success">Plaza disponible</span>
                                @else
                                    <span class="badge bg-warning text-dark">Entrará en lista de espera</span>
                                @endif
                            </dd>
                        </dl>

                        <div class="mb-3">
                            <label class="form-label">Notas para el TS del centro receptor <span class="text-muted">(opcional)</span></label>
                            <textarea wire:model="notas" class="form-control form-control-sm" rows="3"
                                      placeholder="Información relevante para la gestión de la plaza..."></textarea>
                        </div>

                        {{-- Selector de compromiso del plan (solo si hay compromisos sugeridos) --}}
                        @if($this->compromisosSugeridos->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label">Vincular a un compromiso del plan <span class="text-muted">(opcional)</span></label>
                                <select wire:model="compromisoId" class="form-select form-select-sm">
                                    <option value="">— Sin vincular —</option>
                                    @foreach($this->compromisosSugeridos as $compromiso)
                                        <option value="{{ $compromiso->id }}">
                                            {{ $compromiso->prestacion?->nombre ?? 'Compromiso #'.$compromiso->id }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">El vínculo es de referencia; el estado del plan no cambia.</div>
                            </div>
                        @endif

                        @error('destino')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

            </div>

            {{-- Footer con navegación --}}
            <div class="modal-footer">
                @if($paso > 1)
                    <button type="button" class="btn btn-outline-secondary" wire:click="retroceder">
                        ← Anterior
                    </button>
                @endif

                <button type="button" class="btn btn-outline-secondary ms-auto" wire:click="cancelar">
                    Cancelar
                </button>

                @if($paso < 3)
                    <button type="button" class="btn btn-primary" wire:click="avanzar"
                            @if($paso === 1 && !$tipoRecurso) disabled @endif
                            @if($paso === 2 && !$destinoId) disabled @endif>
                        Siguiente →
                    </button>
                @else
                    <button type="button" class="btn btn-success" wire:click="confirmar">
                        Confirmar prescripción
                    </button>
                @endif
            </div>

        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif
</div>
